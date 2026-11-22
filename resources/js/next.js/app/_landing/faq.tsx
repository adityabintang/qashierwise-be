"use client";

import { useState } from "react";
import { ChevIcon } from "./icons";
import { Container, SectionHead } from "./section-shared";
import { t } from "../../lib/i18n";

export function FAQ() {
  const [open, setOpen] = useState<number>(0);

  return (
    <section id="faq" className="pt-10 pb-24">
      <Container>
        <SectionHead eyebrowText={t.faq.eyebrow} title={t.faq.title} subtitle={t.faq.subtitle} />
        <div className="max-w-[720px] mx-auto reveal">
          {t.faq.items.map((it, i) => {
            const isOpen = open === i;
            return (
              <div
                key={it.q}
                className={`bg-white border rounded-[18px] mb-2.5 overflow-hidden transition-all ${
                  isOpen ? "border-purple-200 shadow-[0_6px_20px_rgba(124,58,237,0.08)]" : "border-ink-100"
                }`}
              >
                <button
                  type="button"
                  onClick={() => setOpen(isOpen ? -1 : i)}
                  className="py-[18px] px-[22px] flex justify-between items-center text-[15px] font-semibold cursor-pointer w-full text-left"
                >
                  <span>{it.q}</span>
                  <span
                    className={`w-[26px] h-[26px] rounded-full grid place-items-center transition-all flex-shrink-0 ml-4 ${
                      isOpen ? "bg-purple-100 text-purple-700 rotate-180" : "bg-ink-50 text-ink-500"
                    }`}
                    style={{ transitionTimingFunction: "var(--ease-spring)", transitionDuration: "350ms" }}
                  >
                    <ChevIcon />
                  </span>
                </button>
                <div
                  className="overflow-hidden text-ink-500 text-[14.5px] leading-[1.6]"
                  style={{
                    transition: "max-height 450ms var(--ease-spring), padding 350ms",
                    maxHeight: isOpen ? "240px" : "0px",
                    padding: isOpen ? "0 22px 18px" : "0 22px",
                  }}
                >
                  {it.a}
                </div>
              </div>
            );
          })}
        </div>
      </Container>
    </section>
  );
}
