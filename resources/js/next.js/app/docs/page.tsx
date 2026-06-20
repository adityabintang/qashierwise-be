import { useCallback, useEffect, useMemo, useState } from "react";
import { getDoc } from "./content";
import { DEFAULT_SLUG, DOCS_BASE, flatSlugs } from "./nav";
import { Sidebar } from "./sidebar";
import { MarkdownView } from "./markdown";
import { Nav } from "../_landing/nav";

const BRAND = "#4910ce";

function slugFromPath(): string {
  if (typeof window === "undefined") return DEFAULT_SLUG;
  const path = window.location.pathname.replace(/\/+$/, "");
  const rest = path.startsWith(DOCS_BASE) ? path.slice(DOCS_BASE.length) : "";
  const slug = rest.replace(/^\/+/, "");
  return slug || DEFAULT_SLUG;
}

export default function DocsPage() {
  const [slug, setSlug] = useState<string>(slugFromPath);
  const [mobileOpen, setMobileOpen] = useState(false);

  const navigate = useCallback((next: string) => {
    setSlug(next);
    setMobileOpen(false);
    window.history.pushState({ slug: next }, "", `${DOCS_BASE}/${next}`);
    window.scrollTo({ top: 0, behavior: "auto" });
  }, []);

  // Sync with browser back/forward.
  useEffect(() => {
    const onPop = () => setSlug(slugFromPath());
    window.addEventListener("popstate", onPop);
    return () => window.removeEventListener("popstate", onPop);
  }, []);

  const source = getDoc(slug);

  const { prev, next } = useMemo(() => {
    const idx = flatSlugs.findIndex((s) => s.slug === slug);
    return {
      prev: idx > 0 ? flatSlugs[idx - 1] : null,
      next: idx >= 0 && idx < flatSlugs.length - 1 ? flatSlugs[idx + 1] : null,
    };
  }, [slug]);

  return (
    <div className="min-h-screen bg-white text-gray-900" style={{ fontFamily: "var(--font-manrope, system-ui, sans-serif)" }}>
      {/* Shared site navbar (same as landing/root) */}
      <Nav />

      {/* Mobile-only bar to open the docs sidebar (the shared navbar has no sidebar toggle) */}
      <div className="sticky top-16 z-30 border-b border-gray-100 bg-white/85 backdrop-blur-md lg:hidden">
        <div className="mx-auto flex max-w-7xl items-center px-4 py-2.5 sm:px-6">
          <button
            type="button"
            onClick={() => setMobileOpen((o) => !o)}
            className="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700"
            aria-label="Buka menu dokumentasi"
          >
            <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
              <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            Menu Dokumentasi
          </button>
        </div>
      </div>

      <div className="mx-auto flex max-w-7xl gap-8 px-4 sm:px-6 lg:px-8">
        {/* Sidebar — desktop */}
        <aside className="sticky top-16 hidden h-[calc(100vh-4rem)] w-64 shrink-0 overflow-y-auto py-8 pr-2 lg:block">
          <Sidebar current={slug} onNavigate={navigate} />
        </aside>

        {/* Sidebar — mobile drawer */}
        {mobileOpen && (
          <div className="fixed inset-0 z-40 lg:hidden">
            <div className="absolute inset-0 bg-black/30" onClick={() => setMobileOpen(false)} />
            <div className="absolute left-0 top-0 h-full w-72 overflow-y-auto bg-white p-5 shadow-xl">
              <Sidebar current={slug} onNavigate={navigate} />
            </div>
          </div>
        )}

        {/* Content */}
        <main className="min-w-0 flex-1 py-8 lg:py-10">
          <article className="max-w-3xl">
            {source ? (
              <MarkdownView source={source} onNavigate={navigate} />
            ) : (
              <div className="rounded-xl border border-gray-200 p-8 text-center">
                <h1 className="text-xl font-semibold text-gray-900">Halaman tidak ditemukan</h1>
                <p className="mt-2 text-gray-600">
                  Dokumentasi untuk <code className="rounded bg-gray-100 px-1.5 py-0.5">{slug}</code> belum tersedia.
                </p>
                <button
                  type="button"
                  onClick={() => navigate(DEFAULT_SLUG)}
                  className="mt-4 rounded-lg px-4 py-2 text-sm font-medium text-white"
                  style={{ background: BRAND }}
                >
                  Kembali ke Overview
                </button>
              </div>
            )}

            {/* Prev / Next */}
            {source && (
              <nav className="mt-12 grid grid-cols-1 gap-3 border-t border-gray-100 pt-6 sm:grid-cols-2">
                {prev ? (
                  <button
                    type="button"
                    onClick={() => navigate(prev.slug)}
                    className="group rounded-xl border border-gray-200 p-4 text-left transition-colors hover:border-purple-300"
                  >
                    <div className="text-xs text-gray-400">Sebelumnya</div>
                    <div className="mt-0.5 font-medium text-gray-900">{prev.label}</div>
                  </button>
                ) : (
                  <span />
                )}
                {next && (
                  <button
                    type="button"
                    onClick={() => navigate(next.slug)}
                    className="group rounded-xl border border-gray-200 p-4 text-right transition-colors hover:border-purple-300 sm:col-start-2"
                  >
                    <div className="text-xs text-gray-400">Selanjutnya</div>
                    <div className="mt-0.5 font-medium text-gray-900">{next.label}</div>
                  </button>
                )}
              </nav>
            )}
          </article>
        </main>
      </div>
    </div>
  );
}
