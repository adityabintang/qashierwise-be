import { AiIcon, QrIcon, UsersIcon, WhatsAppIcon } from "./icons";
import { Container, SectionHead } from "./section-shared";
import { t } from "../../lib/i18n";

const icons = [WhatsAppIcon, AiIcon, QrIcon, UsersIcon];
const colors = [
  "from-[#5BD577] to-ios-green",
  "from-purple-400 to-purple-700",
  "from-[#5AAEFF] to-ios-blue",
  "from-[#FFB85C] to-ios-orange",
];

export function HowItWorks() {
  return (
    <section id="how" className="pt-10 pb-24">
      <Container>
        <SectionHead
          eyebrowText={t.how.eyebrow}
          title={t.how.title}
          subtitle={t.how.subtitle}
        />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {t.how.steps.map((s, i) => {
            const Icon = icons[i];
            return (
              <div
                key={i}
                className={`reveal reveal-delay-${i + 1} bg-white border border-ink-100 rounded-[28px] p-6 transition-all duration-[400ms] hover:-translate-y-1 hover:shadow-card hover:border-ink-200 relative overflow-hidden`}
              >
                <div className="text-xs font-bold text-ink-400 tracking-[0.08em]">0{i + 1}</div>
                <div
                  className={`w-12 h-12 rounded-[14px] grid place-items-center my-4 text-white bg-gradient-to-br ${colors[i]} shadow-[0_6px_14px_rgba(0,0,0,0.1),inset_0_1px_0_rgba(255,255,255,0.25)]`}
                >
                  <Icon />
                </div>
                <h3 className="text-[17px] font-bold -tracking-[0.015em]">{s.title}</h3>
                <p className="mt-1.5 text-sm leading-[1.5] text-ink-500">{s.desc}</p>
              </div>
            );
          })}
        </div>
      </Container>
    </section>
  );
}
