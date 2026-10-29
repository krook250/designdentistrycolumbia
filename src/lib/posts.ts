// Blog posts live in the staff portal's database. The build reads them from the portal API.
// If the API can't be reached, the build FAILS on purpose, so a deploy never ships a site without its blog.

export type Post = {
  slug: string;
  title: string;
  metaTitle: string | null;
  description: string;
  html: string;
  image: string | null;
  author: string;
  date: Date;
  updated: Date;
};

const API = (process.env.PORTAL_API || 'https://0mp.129.mytemp.website').replace(/\/$/, '');

let cache: Promise<Post[]> | null = null;

export function getPosts(): Promise<Post[]> {
  if (!cache) cache = load();
  return cache;
}

async function load(): Promise<Post[]> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (process.env.BUILD_KEY) headers['X-Build-Key'] = process.env.BUILD_KEY;
  let res: Response;
  try {
    res = await fetch(`${API}/portal/api/public/posts.php`, { headers });
  } catch (e) {
    throw new Error(`Blog: couldn't reach ${API} (${(e as Error).message}). Build stopped so the live blog isn't wiped.`);
  }
  if (!res.ok) throw new Error(`Blog: ${API} answered ${res.status}. Build stopped so the live blog isn't wiped.`);
  const data = (await res.json()) as { posts: any[] };
  if (!Array.isArray(data.posts) || data.posts.length === 0) {
    throw new Error('Blog: the portal returned no posts. Build stopped so the live blog isn\'t wiped.');
  }
  return data.posts.map((p) => ({ ...p, date: new Date(p.date), updated: new Date(p.updated) }));
}
