import { ArrowIcon } from "./icons";
import { DemoVideoButton } from "./demo-video-button";
import { Container } from "./section-shared";

export function CTABand() {
  return (
    <section className="mt-10">
      <Container>
        <div className="cta-glow reveal relative overflow-hidden rounded-[36px] bg-gradient-to-br from-purple-700 to-purple-900 text-white py-[60px] px-10 text-center">
          <div className="relative z-[1] inline-flex items-center gap-2 text-[13px] font-semibold tracking-wider text-white px-3 py-1.5 rounded-full border border-white/20 bg-white/15">
            <span
              className="w-1.5 h-1.5 rounded-full bg-white animate-pulse-dot"
              style={{ boxShadow: "0 0 0 4px rgba(255,255,255,0.25)" }}
            />
            Siap mencoba?
          </div>
          <h2 className="relative z-[1] mt-[18px] font-extrabold -tracking-[0.03em] leading-[1.1] text-[clamp(28px,4vw,44px)]">
            Mulai dengan gratis hari ini.
            <br />
            Upgrade saat Anda siap.
          </h2>
          <p className="relative z-[1] mt-3.5 text-white/75 text-base">
            Tanpa kartu kredit · Setup &lt; 10 menit · Bahasa Indonesia
          </p>
          <div className="relative z-[1] mt-7 inline-flex gap-2.5 flex-wrap justify-center">
            <a
              href="/login"
              className="inline-flex items-center justify-center gap-2 h-12 px-[22px] rounded-full font-semibold text-[15px] bg-white text-ink-900 hover:bg-ink-50 hover:-translate-y-px transition-all shadow-[0_6px_16px_rgba(11,10,15,0.18)]"
            >
              Coba Gratis 14 Hari <ArrowIcon />
            </a>
            <DemoVideoButton variant="light" />
          </div>
        </div>
      </Container>
    </section>
  );
}
