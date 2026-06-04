import { IgIcon, TtIcon, YtIcon } from "./icons";
import { Container } from "./section-shared";

const navLinks = [
  { href: "/#features", label: "Fitur" },
  { href: "/#pricing", label: "Harga" },
  { href: "/#about", label: "Tentang Kami" },
  { href: "/#faq", label: "FAQ" },
];

const legalLinks = [
  { href: "/privacy-policy", label: "Kebijakan Privasi" },
  { href: "/terms-of-service", label: "Ketentuan Layanan" },
  { href: "/refund-policy", label: "Kebijakan Pengembalian" },
];

const productLinks = [
  { href: "#", label: "QashierWise Console" },
  { href: "#", label: "Chatbot WhatsApp" },
  { href: "#", label: "QRIS Integration" },
];

export function Footer() {
  return (
    <footer className="border-t border-ink-100 pt-14 pb-8 mt-24 bg-[#FAF8FE]">
      <Container>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-10 sm:[grid-template-columns:1.5fr_1fr_1fr_1fr]">
          <div className="col-span-2 sm:col-span-1">
            <div className="flex items-center gap-2.5 font-extrabold text-[18px] -tracking-[0.02em]">
              <div className="w-[30px] h-[30px] rounded-[9px] bg-gradient-to-br from-purple-500 to-purple-700 grid place-items-center text-white font-extrabold text-base shadow-[0_4px_10px_rgba(124,58,237,0.3),inset_0_1px_0_rgba(255,255,255,0.4)]">
                Q
              </div>
              <span>QashierWise</span>
            </div>
            <p className="mt-3.5 text-ink-500 text-sm leading-[1.55] max-w-[280px]">
              Cara gratis 14 hari. QashierWise membantu restoran menerima reservasi & order via WhatsApp dengan mudah.
            </p>
            <div className="mt-4 text-[13px] text-ink-500">
              <div className="font-bold text-ink-700 mb-1">Alamat</div>
              Jl. Widosari No. 55, Tegalrejo Raya
              <br />
              Salatiga, Jawa Tengah, Indonesia 50733
            </div>
          </div>

          {[
            { title: "Navigasi", links: navLinks },
            { title: "Legal", links: legalLinks },
            { title: "Produk", links: productLinks },
          ].map((col) => (
            <div key={col.title}>
              <h4 className="text-[13px] font-bold mb-3.5 tracking-[0.04em] uppercase text-ink-400">{col.title}</h4>
              <ul className="list-none p-0 m-0 grid gap-2.5">
                {col.links.map((l) => (
                  <li key={l.label}>
                    <a href={l.href} className="text-[14.5px] text-ink-700 hover:text-purple-700 transition-colors">
                      {l.label}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>

        <div className="mt-12 pt-6 border-t border-ink-100 flex justify-between items-center text-[13px] text-ink-400 flex-wrap gap-3">
          <div>© 2026 QashierWise by Aditya Bintang Fadila. All Rights Reserved.</div>
          <div className="flex gap-2">
            {[IgIcon, TtIcon, YtIcon].map((Icon, i) => (
              <a
                key={i}
                href="#"
                className="w-[34px] h-[34px] rounded-[10px] bg-white border border-ink-100 grid place-items-center text-ink-500 transition-all hover:text-purple-700 hover:border-purple-200 hover:-translate-y-0.5"
              >
                <Icon />
              </a>
            ))}
          </div>
        </div>
      </Container>
    </footer>
  );
}
