import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { Placeholder } from '@tiptap/extensions';
import { api, ApiError, requireUser } from './portal';

type Post = {
  id: number;
  slug: string;
  title: string;
  meta_title: string | null;
  description: string | null;
  body_html: string;
  image: string | null;
  author: string;
  status: 'draft' | 'scheduled' | 'published';
  publish_at: number | null;
  published_at: number | null;
  updated_at: number;
  legacy: boolean;
};
type SaveRes = { post: Post; site_update: 'started' | 'failed' | 'none'; site_update_problem: string | null };

const $ = <T extends HTMLElement = HTMLElement>(sel: string) => document.querySelector<T>(sel)!;

export function initPostEditor() {
  const titleEl = $<HTMLTextAreaElement>('[data-title]');
  const slugText = $('[data-slug-text]');
  const slugEditBtn = $<HTMLButtonElement>('[data-slug-edit]');
  const slugLocked = $('[data-slug-locked]');
  const slugRow = $('[data-slug-row]');
  const slugInput = $<HTMLInputElement>('[data-slug-input]');
  const stateEl = $('[data-state]');
  const flash = $('[data-flash]');
  const saveBtn = $<HTMLButtonElement>('[data-save]');
  const publishBtn = $<HTMLButtonElement>('[data-publish]');
  const viewLink = $<HTMLAnchorElement>('[data-view]');
  const atRow = $('[data-at-row]');
  const atInput = $<HTMLInputElement>('[data-at]');
  const authorSel = $<HTMLSelectElement>('[data-author]');
  const desc = $<HTMLTextAreaElement>('[data-desc]');
  const descCount = $('[data-desc-count]');
  const featImg = $<HTMLImageElement>('[data-feat-img]');
  const featLabel = $('[data-feat-label]');
  const featRemove = $<HTMLButtonElement>('[data-feat-remove]');
  const featFile = $<HTMLInputElement>('[data-feat-file]');
  const inlineFile = $<HTMLInputElement>('[data-inline-file]');
  const linkbar = $('[data-linkbar]');
  const linkUrl = $<HTMLInputElement>('[data-link-url]');
  const danger = $('[data-danger]');
  const unpublishBtn = $<HTMLButtonElement>('[data-unpublish]');
  const deleteBtn = $<HTMLButtonElement>('[data-delete]');

  let post: Post | null = null;
  let slugTouched = false;
  let image: string | null = null;
  let dirty = false;
  let busy = false;

  const editor = new Editor({
    element: $('[data-editor]'),
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3, 4] },
        code: false,
        codeBlock: false,
        link: { openOnClick: false, autolink: true, defaultProtocol: 'https' },
      }),
      Image.configure({ inline: false }),
      Placeholder.configure({ placeholder: 'Start writing…' }),
    ],
    content: '',
    onUpdate: () => markDirty(),
    onSelectionUpdate: () => syncToolbar(),
    onTransaction: () => syncToolbar(),
  });

  // ---------- helpers ----------
  const isLive = () => !!post && (post.status === 'published' || (post.status === 'scheduled' && !!post.publish_at && post.publish_at * 1000 <= Date.now()));
  const slugLockedNow = () => !!post && (!!post.published_at || isLive());

  function slugify(s: string) {
    return s.toLowerCase().replace(/&/g, ' and ').replace(/[’']/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120) || 'post';
  }
  function currentSlug() { return slugLockedNow() ? post!.slug : (slugTouched ? slugInput.value.trim() : slugify(titleEl.value || 'new-post')); }

  function markDirty() {
    dirty = true;
    renderState();
    renderSerp();
  }

  function showFlash(kind: 'ok' | 'error' | 'warn', msg: string) {
    flash.className = 'p-alert ' + (kind === 'ok' ? 'p-alert--ok' : kind === 'error' ? 'p-alert--error' : '');
    if (kind === 'warn') flash.setAttribute('style', 'background:var(--p-amber-soft);color:#5c3a00');
    else flash.removeAttribute('style');
    flash.textContent = msg;
    flash.hidden = false;
    flash.scrollIntoView({ block: 'nearest' });
  }

  const fmtWhen = (ts: number) => new Date(ts * 1000).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

  function renderState() {
    let s = 'New draft';
    if (post) {
      if (isLive()) s = 'Published';
      else if (post.status === 'scheduled' && post.publish_at) s = `Scheduled for ${fmtWhen(post.publish_at)}`;
      else s = 'Draft';
    }
    stateEl.textContent = s + (dirty ? ' · unsaved changes' : post ? ' · saved' : '');

    const live = isLive();
    const scheduled = !!post && post.status === 'scheduled' && !live;
    saveBtn.textContent = live ? 'Update live post' : scheduled ? 'Save' : 'Save draft';
    saveBtn.classList.toggle('p-btn--primary', live);
    publishBtn.hidden = live;
    const later = (document.querySelector<HTMLInputElement>('input[name=when]:checked')?.value) === 'later';
    publishBtn.textContent = later ? (scheduled ? 'Reschedule' : 'Schedule') : 'Publish now';
    // A live post has nothing left to schedule.
    $('[data-when]').hidden = live;
    atRow.hidden = live || !later;

    viewLink.hidden = !live;
    if (live) viewLink.href = `/${post!.slug}`;
    danger.hidden = !post;
    unpublishBtn.hidden = !(post && (live || scheduled));
    unpublishBtn.textContent = live ? 'Unpublish (take off the site)' : 'Cancel the schedule';
    deleteBtn.hidden = !(post && post.status === 'draft');

    const locked = slugLockedNow();
    slugText.textContent = currentSlug();
    slugEditBtn.hidden = locked || !slugRow.hidden;
    slugLocked.hidden = !locked;
    document.title = `${titleEl.value || 'New post'} · Staff Portal`;
  }

  function renderSerp() {
    const d = desc.value.trim();
    descCount.textContent = `${d.length} characters${d.length > 160 ? ' · Google usually cuts off around 160' : ''}`;
    $('[data-serp-url]').textContent = `designdentistrycolumbia.com › ${currentSlug()}`;
    $('[data-serp-title]').textContent = titleEl.value.trim() || 'Post title';
    $('[data-serp-desc]').textContent = d || (editor.getText().trim().slice(0, 155) + (editor.getText().length > 155 ? '…' : '')) || 'Description';
  }

  function renderImage() {
    featImg.hidden = !image;
    if (image) featImg.src = image;
    featLabel.textContent = image ? 'Replace image' : 'Upload an image';
    featRemove.hidden = !image;
  }

  function autosizeTitle() {
    titleEl.style.height = 'auto';
    titleEl.style.height = titleEl.scrollHeight + 'px';
  }

  function fill(p: Post) {
    post = p;
    titleEl.value = p.title;
    slugInput.value = p.slug;
    slugTouched = true;
    desc.value = p.description || '';
    image = p.image;
    authorSel.value = ['Dr. Isaiah Davis', 'Design Dentistry Team'].includes(p.author) ? p.author : 'Dr. Isaiah Davis';
    editor.commands.setContent(p.body_html || '', { emitUpdate: false });
    if (p.status === 'scheduled' && p.publish_at) {
      (document.querySelector<HTMLInputElement>('input[name=when][value=later]')!).checked = true;
      atInput.value = toLocalInput(p.publish_at);
    }
    dirty = false;
    autosizeTitle();
    renderImage();
    renderState();
    renderSerp();
  }

  function toLocalInput(ts: number) {
    const d = new Date(ts * 1000);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  // ---------- save / publish ----------
  async function send(action: 'save' | 'publish' | 'schedule' | 'unpublish') {
    if (busy) return;
    flash.hidden = true;
    const body: Record<string, unknown> = {
      id: post?.id,
      action,
      title: titleEl.value.trim(),
      slug: slugLockedNow() ? undefined : currentSlug(),
      body_html: editor.getHTML(),
      description: desc.value.trim(),
      image: image || '',
      author: authorSel.value,
    };
    if (action === 'schedule') {
      const t = atInput.value ? new Date(atInput.value).getTime() : NaN;
      if (isNaN(t)) { showFlash('error', 'Pick the date and time it should go live.'); return; }
      body.publish_at = Math.round(t / 1000);
    }
    busy = true;
    [saveBtn, publishBtn].forEach((b) => (b.disabled = true));
    try {
      const res = await api<SaveRes>('blog/save.php', { body });
      const wasNew = !post;
      fill(res.post);
      if (wasNew) history.replaceState(null, '', `/portal/post?id=${res.post.id}`);
      const msgs: Record<string, string> = {
        save: isLive() ? 'Changes saved. The website updates in about a minute.' : 'Draft saved.',
        publish: 'Published. The post will be on the website in about a minute.',
        schedule: `Scheduled for ${res.post.publish_at ? fmtWhen(res.post.publish_at) : 'later'}.`,
        unpublish: 'Taken off the website. It’s a draft again.',
      };
      if (res.site_update === 'failed') showFlash('warn', `Saved, but the website didn’t update: ${res.site_update_problem}`);
      else showFlash('ok', msgs[action]);
    } catch (e) {
      const err = e as ApiError;
      showFlash('error', err.message);
      if (err.body?.field === 'title') titleEl.focus();
      if (err.body?.field === 'slug') { slugRow.hidden = false; slugInput.focus(); renderState(); }
    } finally {
      busy = false;
      [saveBtn, publishBtn].forEach((b) => (b.disabled = false));
    }
  }

  saveBtn.addEventListener('click', () => send('save'));
  publishBtn.addEventListener('click', () => {
    const later = (document.querySelector<HTMLInputElement>('input[name=when]:checked')?.value) === 'later';
    send(later ? 'schedule' : 'publish');
  });
  unpublishBtn.addEventListener('click', () => {
    if (!unpublishBtn.dataset.confirm) {
      unpublishBtn.dataset.confirm = '1';
      unpublishBtn.textContent = isLive() ? 'Click again to take it off the site' : 'Click again to cancel the schedule';
      return;
    }
    delete unpublishBtn.dataset.confirm;
    send('unpublish');
  });
  deleteBtn.addEventListener('click', async () => {
    if (!post) return;
    if (!deleteBtn.dataset.confirm) { deleteBtn.dataset.confirm = '1'; deleteBtn.textContent = 'Click again to delete for good'; return; }
    try {
      await api('blog/delete.php', { body: { id: post.id } });
      dirty = false;
      location.replace('/portal/blog?tab=drafts');
    } catch (e) { showFlash('error', (e as Error).message); }
  });

  document.querySelectorAll<HTMLInputElement>('input[name=when]').forEach((r) => r.addEventListener('change', () => {
    if (r.value === 'later' && !atInput.value) {
      const d = new Date(Date.now() + 24 * 3600 * 1000); d.setHours(9, 0, 0, 0);
      atInput.value = toLocalInput(d.getTime() / 1000);
    }
    renderState();
  }));

  // ---------- title / slug / description ----------
  titleEl.addEventListener('input', () => { autosizeTitle(); markDirty(); });
  titleEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); editor.commands.focus('start'); } });
  slugEditBtn.addEventListener('click', () => { slugInput.value = currentSlug(); slugRow.hidden = false; slugInput.focus(); renderState(); });
  $<HTMLButtonElement>('[data-slug-done]').addEventListener('click', () => {
    slugInput.value = slugify(slugInput.value);
    slugTouched = true;
    slugRow.hidden = true;
    markDirty();
  });
  desc.addEventListener('input', markDirty);
  authorSel.addEventListener('change', markDirty);
  atInput.addEventListener('change', markDirty);

  // ---------- toolbar ----------
  const toolbar = $('[data-toolbar]');
  const cmds: Record<string, () => void> = {
    h2: () => editor.chain().focus().toggleHeading({ level: 2 }).run(),
    h3: () => editor.chain().focus().toggleHeading({ level: 3 }).run(),
    bold: () => editor.chain().focus().toggleBold().run(),
    italic: () => editor.chain().focus().toggleItalic().run(),
    bullet: () => editor.chain().focus().toggleBulletList().run(),
    ordered: () => editor.chain().focus().toggleOrderedList().run(),
    quote: () => editor.chain().focus().toggleBlockquote().run(),
    undo: () => editor.chain().focus().undo().run(),
    redo: () => editor.chain().focus().redo().run(),
    image: () => inlineFile.click(),
    link: () => {
      linkbar.hidden = !linkbar.hidden;
      if (!linkbar.hidden) { linkUrl.value = editor.getAttributes('link').href || ''; linkUrl.focus(); }
    },
  };
  toolbar.addEventListener('mousedown', (e) => { if ((e.target as HTMLElement).closest('button')) e.preventDefault(); });
  toolbar.addEventListener('click', (e) => {
    const b = (e.target as HTMLElement).closest<HTMLButtonElement>('button[data-cmd]');
    if (b) cmds[b.dataset.cmd!]?.();
  });
  function syncToolbar() {
    const on: Record<string, boolean> = {
      h2: editor.isActive('heading', { level: 2 }), h3: editor.isActive('heading', { level: 3 }),
      bold: editor.isActive('bold'), italic: editor.isActive('italic'), link: editor.isActive('link'),
      bullet: editor.isActive('bulletList'), ordered: editor.isActive('orderedList'), quote: editor.isActive('blockquote'),
    };
    toolbar.querySelectorAll<HTMLButtonElement>('button[data-cmd]').forEach((b) => {
      const k = b.dataset.cmd!;
      if (k in on) { b.classList.toggle('is-on', on[k]); b.setAttribute('aria-pressed', String(on[k])); }
    });
    (toolbar.querySelector('[data-cmd=undo]') as HTMLButtonElement).disabled = !editor.can().undo();
    (toolbar.querySelector('[data-cmd=redo]') as HTMLButtonElement).disabled = !editor.can().redo();
  }
  function applyLink() {
    let href = linkUrl.value.trim();
    if (!href) { editor.chain().focus().extendMarkRange('link').unsetLink().run(); linkbar.hidden = true; return; }
    if (!/^(https?:\/\/|mailto:|tel:|\/)/i.test(href)) href = (/^[\w.-]+\.[a-z]{2,}/i.test(href) ? 'https://' : '/') + href.replace(/^\/+/, '');
    if (editor.state.selection.empty && !editor.isActive('link')) {
      editor.chain().focus().insertContent(`<a href="${href.replace(/"/g, '&quot;')}">${linkUrl.value.trim().replace(/</g, '&lt;')}</a> `).run();
    } else {
      editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
    }
    linkbar.hidden = true;
  }
  $<HTMLButtonElement>('[data-link-apply]').addEventListener('click', applyLink);
  linkUrl.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); applyLink(); } if (e.key === 'Escape') linkbar.hidden = true; });
  $<HTMLButtonElement>('[data-link-remove]').addEventListener('click', () => { editor.chain().focus().extendMarkRange('link').unsetLink().run(); linkbar.hidden = true; });

  // ---------- images ----------
  async function upload(file: File): Promise<string | null> {
    const fd = new FormData();
    fd.append('image', file);
    const csrfRes = await api<{ csrf: string }>('me.php'); // refreshes the CSRF token
    const res = await fetch('/portal/api/blog/upload.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrfRes.csrf } });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) { showFlash('error', data.error || 'The image didn’t upload.'); return null; }
    return data.url as string;
  }
  inlineFile.addEventListener('change', async () => {
    const f = inlineFile.files?.[0];
    inlineFile.value = '';
    if (!f) return;
    showFlash('ok', 'Uploading image…');
    const url = await upload(f);
    if (url) { editor.chain().focus().setImage({ src: url, alt: '' }).run(); flash.hidden = true; }
  });
  $<HTMLButtonElement>('[data-feat-pick]').addEventListener('click', () => featFile.click());
  featFile.addEventListener('change', async () => {
    const f = featFile.files?.[0];
    featFile.value = '';
    if (!f) return;
    featLabel.textContent = 'Uploading…';
    const url = await upload(f);
    if (url) { image = url; markDirty(); }
    renderImage();
  });
  featRemove.addEventListener('click', () => { image = null; renderImage(); markDirty(); });

  // ---------- leaving with unsaved work ----------
  window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); send('save'); }
  });

  // ---------- load ----------
  requireUser().then(async () => {
    const id = Number(new URLSearchParams(location.search).get('id') || 0);
    if (id) {
      try {
        const { post: p } = await api<{ post: Post }>(`blog/get.php?id=${id}`);
        fill(p);
      } catch (e) {
        showFlash('error', (e as Error).message);
      }
    } else {
      renderImage();
      renderState();
      renderSerp();
      titleEl.focus();
    }
    syncToolbar();
  });
}
