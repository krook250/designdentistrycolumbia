<?php
/*
 * Image upload for posts. Saves to /uploads/blog/YYYY/MM/<random>.<ext> in the web root
 * (outside anything the deploy manages), resized to at most 1600px wide when GD is available.
 */
declare(strict_types=1);
require __DIR__ . '/../_lib/bootstrap.php';
require_method('POST');
check_csrf();
require_user();

$f = $_FILES['image'] ?? null;
if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $code = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    json_out(422, ['error' => in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'That image is too big. Keep it under 10 MB.' : 'The upload didn’t come through. Try again.']);
}
if ($f['size'] > 10 * 1024 * 1024) json_out(422, ['error' => 'That image is too big. Keep it under 10 MB.']);

$info = @getimagesize($f['tmp_name']);
$types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
if (!$info || !isset($types[$info[2]])) json_out(422, ['error' => 'Use a JPG, PNG, WebP or GIF image.']);
[$w, $h] = $info;
$ext = $types[$info[2]];

$webroot = !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') : dirname(__DIR__, 3);
$rel = '/uploads/blog/' . gmdate('Y/m');
$dir = $webroot . $rel;
if (!is_dir($dir) && !mkdir($dir, 0755, true)) json_out(500, ['error' => 'Couldn’t save the image on the server.']);
$name = bin2hex(random_bytes(8));

$saved = false;
$max = 1600;
if ($w > $max && $ext !== 'gif' && function_exists('imagecreatefromstring')) {
    $src = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
    if ($src) {
        $nh = (int)round($h * $max / $w);
        $dst = imagecreatetruecolor($max, $nh);
        if ($ext === 'png' || $ext === 'webp') { imagealphablending($dst, false); imagesavealpha($dst, true); }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $max, $nh, $w, $h);
        $path = "$dir/$name.$ext";
        $saved = match ($ext) {
            'jpg' => imagejpeg($dst, $path, 82),
            'png' => imagepng($dst, $path, 8),
            'webp' => function_exists('imagewebp') ? imagewebp($dst, $path, 82) : false,
            default => false,
        };
        imagedestroy($src);
        imagedestroy($dst);
        if ($saved) { $w = $max; $h = $nh; }
    }
}
if (!$saved) {
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name.$ext")) json_out(500, ['error' => 'Couldn’t save the image on the server.']);
}
@chmod("$dir/$name.$ext", 0644);

json_out(201, ['url' => "$rel/$name.$ext", 'width' => $w, 'height' => $h]);
