import { QrIcon, SparkleIcon, WhatsAppIcon } from "./icons";
import { Container, Eyebrow } from "./section-shared";

type Logo = {
  name: string;
  Icon: React.FC<React.SVGProps<SVGSVGElement>>;
  bg: string;
};

const logos: Logo[] = [
  { name: "WhatsApp Business API", Icon: WhatsAppIcon, bg: "bg-[#25D366]" },
  { name: "AI LLM (OpenAI/Claude)", Icon: SparkleIcon, bg: "bg-gradient-to-br from-purple-500 to-purple-700" },
  { name: "QRIS Midtrans", Icon: QrIcon, bg: "bg-[#0CC0DF]" },
  { name: "QRIS Xendit", Icon: QrIcon, bg: "bg-[#4373F8]" },
  { name: "QRIS OkeOce", Icon: QrIcon, bg: "bg-[#FF6B35]" },
];

export function Integrations() {
  return (
    <section className="text-center pt-5 pb-24">
      <Container>
        <div className="reveal">
          <Eyebrow className="mb-3.5">Integrasi</Eyebrow>
          <h3 className="text-2xl font-bold -tracking-[0.02em]">Integrasi yang didukung</h3>
          <p className="text-ink-500 mt-2.5">Sambungkan QashierWise untuk bisnis Anda — tanpa ribet.</p>
        </div>
        <div className="mt-8 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          {logos.map((l, i) => (
            <div
              key={l.name}
              className={`reveal reveal-delay-${i + 1} bg-white border border-ink-100 rounded-[18px] py-5 px-3 flex items-center justify-center gap-2.5 text-[13.5px] font-semibold text-ink-700 transition-all hover:-translate-y-1 hover:border-purple-200`}
            >
              <span className={`w-7 h-7 rounded-lg grid place-items-center text-white text-sm font-extrabold flex-shrink-0 ${l.bg}`}>
                <l.Icon />
              </span>
              {l.name}
            </div>
          ))}
        </div>
      </Container>
    </section>
  );
}
