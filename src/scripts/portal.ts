// Shared client helpers for the staff portal pages.

export type PortalUser = {
  id: number;
  email: string;
  name: string;
  role: 'owner' | 'staff';
  must_change_password: boolean;
};

let csrf = '';

export class ApiError extends Error {
  status: number;
  body: any;
  constructor(status: number, body: any) {
    super(body?.error || `Request failed (${status})`);
    this.status = status;
    this.body = body;
  }
}

export async function api<T = any>(path: string, opts: { method?: string; body?: unknown } = {}): Promise<T> {
  const method = opts.method || (opts.body !== undefined ? 'POST' : 'GET');
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (opts.body !== undefined) headers['Content-Type'] = 'application/json';
  if (method !== 'GET' && csrf) headers['X-CSRF-Token'] = csrf;
  const res = await fetch(`/portal/api/${path}`, {
    method,
    headers,
    credentials: 'same-origin',
    body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
  });
  let data: any = null;
  try { data = await res.json(); } catch { /* non-JSON error page */ }
  if (!res.ok) {
    if (res.status === 403 && data?.must_change_password && !location.pathname.startsWith('/portal/account')) {
      location.replace('/portal/account');
    }
    throw new ApiError(res.status, data || { error: 'The server sent an unexpected response.' });
  }
  if (data?.csrf) csrf = data.csrf;
  return data as T;
}

/**
 * Call on every signed-in page. Redirects to sign-in, first-time setup, or
 * "set your own password" as needed; fills [data-user-*] slots in the shell.
 */
export async function requireUser(opts: { ownerOnly?: boolean } = {}): Promise<PortalUser> {
  let user: PortalUser;
  try {
    ({ user } = await api<{ user: PortalUser }>('me.php'));
  } catch (e) {
    if (e instanceof ApiError && e.status === 401) {
      location.replace(e.body?.needs_setup ? '/portal/setup' : '/portal/login');
      return new Promise<PortalUser>(() => {}); // page is leaving; never resolve
    }
    throw e;
  }
  if (user.must_change_password && !location.pathname.startsWith('/portal/account')) {
    location.replace('/portal/account');
    return new Promise<PortalUser>(() => {});
  }
  if (opts.ownerOnly && user.role !== 'owner') {
    location.replace('/portal/dashboard');
    return new Promise<PortalUser>(() => {});
  }
  document.querySelectorAll<HTMLElement>('[data-user-name]').forEach((el) => (el.textContent = user.name));
  document.querySelectorAll<HTMLElement>('[data-user-initials]').forEach((el) => (el.textContent = initials(user.name)));
  document.querySelectorAll<HTMLElement>('[data-user-role]').forEach((el) => (el.textContent = user.role === 'owner' ? 'Owner' : 'Staff'));
  document.querySelectorAll<HTMLElement>('[data-owner-only]').forEach((el) => (el.hidden = user.role !== 'owner'));
  document.documentElement.dataset.auth = 'ok';
  return user;
}

export async function signOut() {
  try { await api('logout.php', { method: 'POST' }); } finally { location.replace('/portal/login'); }
}

export function initials(name: string) {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

export function greeting(d = new Date()) {
  const h = d.getHours();
  return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
}

/** "Today, 9:15 AM" / "Yesterday" / "Oct 21" from a Unix timestamp (seconds). */
export function whenText(ts: number | null): string {
  if (!ts) return 'Never';
  const d = new Date(ts * 1000);
  const now = new Date();
  const day = (x: Date) => new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime();
  const diff = Math.round((day(now) - day(d)) / 86400000);
  const time = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  if (diff === 0) return `Today, ${time}`;
  if (diff === 1) return 'Yesterday';
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: d.getFullYear() === now.getFullYear() ? undefined : 'numeric' });
}

export function escapeHtml(s: string) {
  return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]!));
}
