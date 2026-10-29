<?php
declare(strict_types=1);

/*
 * Blog helpers: slugs, HTML cleaning, the public post shape, and the
 * "rebuild the website" trigger (GitHub repository_dispatch).
 */

/** Top-level paths that belong to the website, never to a post. Lowercased for comparison. */
const RESERVED_SLUGS = [
    '404', 'isaiahdavis', 'procedures', 'tooth-fillings', '_astro', 'admin', 'api', 'blog', 'contact', 'corebuildups',
    'cosmetic-dentistry', 'cosmetic-dentistry-near--city--state', 'cristinemobley', 'crowns', 'dental-bridges',
    'dental-sealants', 'dental-veneers', 'favicon', 'feed', 'financing-options', 'franrivera', 'full-arch-dental-implants',
    'general-dentistry', 'home', 'hygiene', 'ike', 'images', 'implants', 'in-the-media', 'index', 'jeromeharper',
    'kayleeboyd', 'location', 'login', 'porcelain-inlays-and-onlays', 'portal', 'realresults', 'restorative-dentistry',
    'robots', 'rootcanal-therapy', 'rss', 'sitemap-0', 'sitemap-index', 'team', 'technology', 'teeth-whitening',
    'testimonials', 'thank-you', 'tooth-extractions', 'uploads', 'vanessa', 'videos', 'wp-admin',
];

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = strtr($s, ['&' => ' and ', '’' => '', "'" => '']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    return substr($s, 0, 120) ?: 'post';
}

/** Returns an error message, or null when the slug is usable for post $id. */
function slug_problem(string $slug, ?int $id): ?string {
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) return 'Use lowercase letters, numbers and dashes for the web address.';
    if (strlen($slug) > 120) return 'Keep the web address under 120 characters.';
    if (in_array($slug, RESERVED_SLUGS, true)) return 'That web address is already a page on the site.';
    $st = db()->prepare('SELECT id FROM posts WHERE slug = ? AND id <> ?');
    $st->execute([$slug, $id ?? 0]);
    if ($st->fetch()) return 'Another post already uses that web address.';
    return null;
}

/**
 * Keep only the formatting the editor can make. Anything else (scripts, styles,
 * event handlers, iframes, unknown tags) is dropped; links must be http(s), mailto, tel or site-relative.
 */
function clean_html(string $html): string {
    $allowed = [
        'p' => [], 'br' => [], 'hr' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'a' => ['href', 'target', 'rel'], 'img' => ['src', 'alt', 'width', 'height'],
        'figure' => [], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
    ];
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $doc->getElementById('__root');
    if (!$root) return '';

    $walk = function (DOMNode $node) use (&$walk, $allowed, $doc) {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                if (!isset($allowed[$tag])) {
                    // Unknown wrapper (span, div, font…): keep its contents, drop the tag.
                    $walk($child);
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attr) {
                    if (!in_array($attr->name, $allowed[$tag], true)) $child->removeAttribute($attr->name);
                }
                if ($tag === 'a') {
                    $href = trim($child->getAttribute('href'));
                    if (!preg_match('#^(https?://|mailto:|tel:|/(?!/)|\#)#i', $href)) $child->removeAttribute('href');
                    if (preg_match('#^https?://#i', $href) && !preg_match('#^https?://(www\.)?designdentistrycolumbia\.com#i', $href)) {
                        $child->setAttribute('target', '_blank');
                        $child->setAttribute('rel', 'noopener');
                    } else {
                        $child->removeAttribute('target');
                        $child->removeAttribute('rel');
                    }
                }
                if ($tag === 'img') {
                    $src = trim($child->getAttribute('src'));
                    if (!preg_match('#^(/(?!/)|https://)#i', $src)) { $node->removeChild($child); continue; }
                    $child->setAttribute('loading', 'lazy');
                }
                $walk($child);
            } elseif ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $node->removeChild($child);
            }
        }
    };
    $walk($root);

    $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    // Drop empty paragraphs the editor leaves behind.
    $out = preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#', '', $out);
    return trim((string)$out);
}

function plain_excerpt(string $html, int $len = 160): string {
    $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (mb_strlen($t) <= $len) return $t;
    $cut = mb_substr($t, 0, $len);
    $sp = mb_strrpos($cut, ' ');
    return rtrim(mb_substr($cut, 0, $sp ?: $len), ' ,.;:') . '…';
}

/** A post is live when published, or scheduled with its time reached. SQL fragment. */
const LIVE_SQL = "(status = 'published' OR (status = 'scheduled' AND publish_at <= UTC_TIMESTAMP()))";

/**
 * Ask GitHub to rebuild and deploy the site. Never throws: returns null on success,
 * or a short reason the website update didn't start.
 */
function trigger_rebuild(string $why): ?string {
    $gh = config()['github'] ?? null;
    if (!is_array($gh) || empty($gh['token']) || empty($gh['repo'])) return 'Website updates aren’t connected yet.';
    $base = rtrim($gh['api'] ?? 'https://api.github.com', '/'); // overridable for local testing only
    $ch = curl_init("$base/repos/{$gh['repo']}/dispatches");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github+json',
            'Authorization: Bearer ' . $gh['token'],
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: ddc-portal',
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode(['event_type' => 'publish', 'client_payload' => ['why' => mb_substr($why, 0, 100)]]),
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($code === 204) {
        db()->prepare("INSERT INTO settings (name, value) VALUES ('last_rebuild_request', JSON_OBJECT('at', UTC_TIMESTAMP(), 'why', ?)) ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute([$why]);
        return null;
    }
    error_log("portal rebuild dispatch failed: HTTP $code $err " . substr((string)$body, 0, 300));
    return $code === 401 || $code === 403 || $code === 404
        ? 'The website update key was refused. It may have expired.'
        : 'The website update didn’t start. Try again in a minute.';
}

function post_for_editor(array $p): array {
    return [
        'id' => (int)$p['id'],
        'slug' => $p['slug'],
        'title' => $p['title'],
        'meta_title' => $p['meta_title'],
        'description' => $p['description'],
        'body_html' => $p['body_html'],
        'image' => $p['image'],
        'author' => $p['author'],
        'status' => $p['status'],
        'publish_at' => $p['publish_at_ts'] !== null ? (int)$p['publish_at_ts'] : null,
        'published_at' => $p['published_at_ts'] !== null ? (int)$p['published_at_ts'] : null,
        'updated_at' => (int)$p['updated_at_ts'],
        'legacy' => (int)$p['legacy'] === 1,
    ];
}

const POST_COLUMNS = 'id, slug, title, meta_title, description, body_html, image, author, status, legacy,
    UNIX_TIMESTAMP(publish_at) AS publish_at_ts, UNIX_TIMESTAMP(published_at) AS published_at_ts, UNIX_TIMESTAMP(updated_at) AS updated_at_ts';

function load_post(int $id): ?array {
    $st = db()->prepare('SELECT ' . POST_COLUMNS . ' FROM posts WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
