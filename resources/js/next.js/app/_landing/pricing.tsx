"use client";

import { useLayoutEffect, useRef, useState } from "react";
import { ArrowIcon, CheckIcon, SparkleIcon } from "./icons";
import { Container, Eyebrow } from "./section-shared";

const options = [
  { label: "1 Bulan", m: 350, save: null },
  { label: "3 Bulan", m: 315, save: null },
  { label: "1 Tahun", m: 245, save: "-30%" as const },
];

const basicFeatures = [
  "AI chatbot dasar (% pesan/bulan)",
  "Reservasi & pickup orders",
  "Webhook menu digital",
  "Tanpa pembayaran online (bayar di tempat)",
  "1 user staff · Email support (24h)",
];

const proFeatures = [
  "Delivery + airtime & bayar",
  "Pembayaran QRIS unlimited",
  "Reservasi & order auto-confirm",
  "All pesan/bulan + Chat support (1hr)",
  "Customer Base",
  "Analytics + export CSV",
  "Webhook & API",
];

export function Pricing() {
  const [billing, setBilling] = useState(1);
  const indicatorRef = useRef<HTMLDivElement>(null);
  const btnRefs = useRef<(HTMLButtonElement | null)[]>([]);

  useLayoutEffect(() => {
    const btn = btnRefs.current[billing];
    const ind = indicatorRef.current;
    if (btn && ind) {
      ind.style.left = btn.offsetLeft + "px";
      ind.style.width = btn.offsetWidth + "px";
    }
  }, [billing]);

  const proPrice = options[billing].m;

  return (
    <section id="pricing" className="py-24">
      <Container>
        <div className="text-center max-w-[720px] mx-auto mb-14 reveal">
          <Eyebrow className="mb-[18px]">
            <SparkleIcon /> Harga
          </Eyebrow>
          <h2 className="font-extrabold leading-[1.05] -tracking-[0.03em] text-[clamp(32px,4vw,52px)]">
            Harga sederhana, tumbuh bersama Anda
          </h2>
          <p className="mt-3.5 text-ink-500 font-medium leading-[1.5] text-[clamp(16px,1.4vw,19px)]">Mulai gratis — upgrade kapan saja.</p>
          <div className="inline-flex mt-3.5 p-1 bg-ink-100 rounded-full relative">
            <div
              ref={indicatorRef}
              className="absolute top-1 bottom-1 bg-white rounded-full shadow-[0_2px_6px_rgba(0,0,0,0.08)] z-[1] transition-[left,width] duration-[400ms]"
              style={{ transitionTimingFunction: "var(--ease-spring)" }}
            />
            {options.map((o, i) => (
              <button
                key={o.label}
                type="button"
                ref={(el) => {
                  btnRefs.current[i] = el;
                }}
                onClick={() => setBilling(i)}
                className={`relative z-[2] py-2 px-4 rounded-full text-[13px] font-semibold inline-flex items-center gap-1.5 transition-colors duration-300 ${
                  billing === i ? "text-ink-900" : "text-ink-500"
                }`}
              >
                {o.label}
                {o.save && (
                  <span className="text-[10px] bg-purple-100 text-purple-700 py-0.5 px-1.5 rounded font-bold">{o.save}</span>
                )}
              </button>
            ))}
          </div>
        </div>

        <div className="grid sm:grid-cols-2 gap-5 max-w-[880px] mx-auto">
          <div className="reveal bg-white border border-ink-100 rounded-[28px] p-8 transition-all hover:-translate-y-1 hover:shadow-card relative">
            <h3 className="text-[22px] font-bold -tracking-[0.02em]">Basic</h3>
            <div className="text-[13.5px] text-ink-500 mt-1.5">1 outlet · No admin · 4 pickup</div>
            <div className="my-[22px] flex items-baseline gap-1.5">
              <span className="text-[44px] font-extrabold -tracking-[0.035em] leading-none">Rp0</span>
              <span className="text-sm text-ink-500 font-medium">/selamanya</span>
            </div>
            <ul className="list-none p-0 m-0 grid gap-2.5">
              {basicFeatures.map((t) => (
                <li key={t} className="flex items-center gap-2.5 text-sm">
                  <span className="inline-grid place-items-center w-[18px] h-[18px] rounded-full bg-purple-100 text-purple-700 flex-shrink-0">
                    <CheckIcon />
                  </span>
                  {t}
                </li>
              ))}
            </ul>
            <a
              href="#"
              className="mt-6 inline-flex items-center justify-center w-full gap-2 h-12 px-[22px] rounded-full font-semibold text-[15px] bg-white text-ink-900 border border-ink-200 hover:bg-ink-50 hover:border-ink-300 transition-all"
            >
              Mulai Gratis
            </a>
          </div>

          <div className="reveal reveal-delay-2 bg-gradient-to-b from-[#1B1430] to-[#2D1B5C] text-white rounded-[28px] p-8 transition-all hover:-translate-y-1 shadow-purple relative">
            <span className="absolute top-6 right-6 text-[10px] font-extrabold tracking-[0.08em] py-1 px-2.5 rounded-full bg-purple-500 uppercase">
              Populer
            </span>
            <h3 className="text-[22px] font-bold -tracking-[0.02em]">Pro</h3>
            <div className="text-[13.5px] text-white/60 mt-1.5">1 outlet · delivery + QRIS</div>
            <div className="my-[22px] flex items-baseline gap-1.5">
              <span className="text-[44px] font-extrabold -tracking-[0.035em] leading-none">Rp{proPrice}.000</span>
              <span className="text-sm text-white/60 font-medium">/bln</span>
            </div>
            <ul className="list-none p-0 m-0 grid gap-2.5">
              {proFeatures.map((t) => (
                <li key={t} className="flex items-center gap-2.5 text-sm text-white/85">
                  <span className="inline-grid place-items-center w-[18px] h-[18px] rounded-full bg-purple-600/30 text-white flex-shrink-0">
                    <CheckIcon />
                  </span>
                  {t}
                </li>
              ))}
            </ul>
            <a
              href="#"
              className="mt-6 inline-flex items-center justify-center w-full gap-2 h-12 px-[22px] rounded-full font-semibold text-[15px] bg-white text-purple-700 hover:-translate-y-px transition-all"
            >
              Pilih Pro <ArrowIcon />
            </a>
          </div>
        </div>
      </Container>
    </section>
  );
}
