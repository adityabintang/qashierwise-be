import { GlobeIcon } from "./icons";
import { DemoVideoButton } from "./demo-video-button";

export function Nav() {
  return (
    <nav className="sticky top-0 z-50 bg-white/70 backdrop-blur-2xl backdrop-saturate-150 border-b border-[rgba(20,12,50,0.06)]">
      <div className="max-w-[1200px] mx-auto px-5 sm:px-7">
        <div className="flex items-center justify-between h-16">
          <a href="/" className="flex items-center gap-2.5 font-extrabold text-[18px] -tracking-[0.02em]">
            <div className="w-[30px] h-[30px] rounded-[9px] bg-gradient-to-br from-purple-500 to-purple-700 grid place-items-center text-white font-extrabold text-base shadow-[0_4px_10px_rgba(124,58,237,0.3),inset_0_1px_0_rgba(255,255,255,0.4)]">
              Q
            </div>
            <span>QashierWise</span>
          </a>

          <div className="hidden lg:flex gap-1 items-center">
            {[
              { href: "/#how", label: "Cara Kerja" },
              { href: "/#features", label: "Fitur" },
              { href: "/#pricing", label: "Harga" },
              { href: "/docs", label: "Docs" },
              { href: "/blog", label: "Blog" },
              { href: "/#about", label: "Tentang" },
              { href: "/#faq", label: "FAQ" },
            ].map((l) => (
              <a
                key={l.href}
                href={l.href}
                className="px-3.5 py-2 rounded-full text-[14.5px] font-medium text-ink-700 hover:bg-ink-50 hover:text-ink-900 transition-colors"
              >
                {l.label}
              </a>
            ))}
          </div>

          <div className="flex gap-2 items-center">
            <span className="inline-flex items-center gap-1.5 h-9 px-3 rounded-full bg-ink-50 text-[13px] font-semibold text-ink-700">
              <GlobeIcon /> ID
            </span>
            <DemoVideoButton variant="sm" className="hidden sm:inline-flex" />
            <a
              href="/login"
              className="inline-flex items-center justify-center gap-2 h-[38px] px-4 rounded-full font-semibold text-sm bg-purple-600 text-white shadow-purple hover:bg-purple-700 hover:-translate-y-px transition-all"
            >
              Coba Gratis 14 Hari
            </a>
          </div>
        </div>
      </div>
    </nav>
  );
}
