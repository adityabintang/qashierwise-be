// Loads every markdown file under `resources/docs/**/*.md` as a raw string at
// build time and exposes them keyed by slug (path relative to `resources/docs`,
// without the `.md` extension). e.g. `katalog/create-product`.
//
// Vite-specific: `import.meta.glob`. When migrating to Next.js, replace this with
// `fs.readFileSync` over the same folder (e.g. in a Server Component / generateStaticParams).

const modules = import.meta.glob("../../../../docs/**/*.md", {
  query: "?raw",
  import: "default",
  eager: true,
}) as Record<string, string>;

const PREFIX = "../../../../docs/";

export const docs: Record<string, string> = {};

for (const [path, raw] of Object.entries(modules)) {
  const slug = path.slice(PREFIX.length).replace(/\.md$/, "");
  docs[slug] = raw;
}

export function getDoc(slug: string): string | undefined {
  return docs[slug];
}

/** Pull the first H1 (`# Title`) out of a markdown string, for page titles. */
export function getTitle(raw: string, fallback = "Dokumentasi"): string {
  const m = raw.match(/^#\s+(.+)$/m);
  return m ? m[1].trim() : fallback;
}
