import { AiIcon, CalendarIcon, CardIcon, ChartIcon, InboxIcon, TruckIcon } from "./icons";
import { Container, SectionHead } from "./section-shared";
import { t } from "../../lib/i18n";

const icons = [AiIcon, InboxIcon, TruckIcon, CalendarIcon, CardIcon, ChartIcon];

export function Features() {
  return (
    <section id="features" className="pt-10 pb-16">
      <Container>
        <SectionHead
          eyebrowText={t.features.eyebrow}
          title={t.features.title}
          subtitle={t.features.subtitle}
        />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-[18px]">
          {t.features.items.map((f, i) => {
            const Icon = icons[i];
            return (
              <div
                key={i}
                className={`reveal reveal-delay-${(i % 3) + 1} bg-white border border-ink-100 rounded-[28px] p-[26px] transition-all duration-[400ms] hover:-translate-y-1 hover:shadow-card hover:border-purple-100 relative overflow-hidden`}
              >
                <div className="w-11 h-11 rounded-xl bg-purple-50 text-purple-700 grid place-items-center mb-4">
                  <Icon />
                </div>
                <h3 className="text-[17px] font-bold">{f.title}</h3>
                <p className="mt-2 text-ink-500 text-[14.5px] leading-[1.5]">{f.desc}</p>
              </div>
            );
          })}
        </div>
      </Container>
    </section>
  );
}
