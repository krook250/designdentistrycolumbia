// Shared client helpers for the staff portal pages.

export type PortalUser = { id: number; email: string; name: string; role: 'owner' | 'staff' };

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
  const method = opts.method || (opts.body ? 'POST' : 'GET');
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
  if (!res.ok) throw new ApiError(res.status, data || { error: 'The server sent an unexpected response.' });
  if (data?.csrf) csrf = data.csrf;
  return data as T;
}

/** Call on every signed-in page. Redirects to sign-in (or first-time setup) when needed. */
export async function requireUser(): Promise<PortalUser> {
  try {
    const { user } = await api<{ user: PortalUser }>('me.php');
    document.querySelectorAll<HTMLElement>('[data-user-name]').forEach((el) => (el.textContent = user.name));
    document.querySelectorAll<HTMLElement>('[data-user-initials]').forEach((el) => (el.textContent = initials(user.name)));
    document.querySelectorAll<HTMLElement>('[data-user-role]').forEach((el) => (el.textContent = user.role === 'owner' ? 'Owner' : 'Staff'));
    document.documentElement.dataset.auth = 'ok';
    return user;
  } catch (e) {
    if (e instanceof ApiError && e.status === 401) {
      location.replace(e.body?.needs_setup ? '/portal/setup' : '/portal/login');
      return new Promise<PortalUser>(() => {}); // page is leaving; never resolve
    }
    throw e;
  }
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
