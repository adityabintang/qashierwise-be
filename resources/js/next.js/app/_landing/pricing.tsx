"use client";

import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { ArrowIcon, CheckIcon, SparkleIcon } from "./icons";
import { Container, Eyebrow } from "./section-shared";
import { t, locale } from "../../lib/i18n";

// ---- Subscription plans (real prices) -------------------------------------
// Injected by the blade host from `config('subscription.plans')`
// (window.__SUBSCRIPTION_PLANS__). The fallback mirrors that config so the
// section still renders correct numbers if the global is missing.
type DurationData = {
  id: string;
  name: string;
  months: number;
  price: number;
  price_per_month: number;
  discount: number;
};
type PlanData = {
  id: string;
  name: string;
  price_monthly: number;
  durations?: Record<string, DurationData>;
};
type Plans = Record<string, PlanData>;

const FALLBACK_PLANS: Plans = {
  pro: {
    id: "pro",
    name: "Pro",
    price_monthly: 350000,
    durations: {
      "1_month": { id: "pro_1_month", name: "1 Bulan", months: 1, price: 350000, price_per_month: 350000, discount: 0 },
      "3_months": { id: "pro_3_months", name: "3 Bulan", months: 3, price: 1050000, price_per_month: 350000, discount: 0 },
      "1_year": { id: "pro_1_year", name: "1 Tahun", months: 12, price: 3780000, price_per_month: 315000, discount: 10 },
    },
  },
};

const PLANS: Plans =
  (typeof window !== "undefined" &&
    (window as unknown as { __SUBSCRIPTION_PLANS__?: Plans }).__SUBSCRIPTION_PLANS__) ||
  FALLBACK_PLANS;

const durationSuffixes = locale === "en"
  ? ["/mo", "/3mo", "/yr"]
  : ["/bln", "/3bln", "/thn"];

const perMonthSuffix = locale === "en" ? "/mo" : "/bln";

const DURATIONS = [
  { key: "1_month" as const, label: t.pricing.options[0], suffix: durationSuffixes[0] },
  { key: "3_months" as const, label: t.pricing.options[1], suffix: durationSuffixes[1] },
  { key: "1_year" as const, label: t.pricing.options[2], suffix: durationSuffixes[2] },
];

const rupiah = (n: number) => "Rp" + new Intl.NumberFormat("id-ID").format(n);

export function Pricing() {
  const [billing, setBilling] = useState(0); // index into DURATIONS (default 1 Bulan)
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const indicatorRef = useRef<HTMLDivElement>(null);
  const btnRefs = useRef<(HTMLButtonElement | null)[]>([]);

  const durationKey = DURATIONS[billing].key;
  const pro = PLANS.pro?.durations?.[durationKey];
  const proTotal = pro?.price ?? 0;
  const proPerMonth = pro?.price_per_month ?? 0;
  const proDiscount = pro?.discount ?? 0;

  // Slide the active-tab pill.
  useLayoutEffect(() => {
    const btn = btnRefs.current[billing];
    const ind = indicatorRef.current;
    if (btn && ind) {
      ind.style.left = btn.offsetLeft + "px";
      ind.style.width = btn.offsetWidth + "px";
    }
  }, [billing]);

  // Start the Pro checkout. Mirrors the old blade flow:
  // - not logged in → bounce to /login, then auto-checkout on return
  // - logged in → POST /api/subscription/checkout, then redirect to Xendit
  async function startProCheckout(forcedDuration?: string) {
    const token = typeof window !== "undefined" ? localStorage.getItem("token") : null;
    const duration = forcedDuration ?? durationKey;

    if (!token) {
      window.location.href = `/login?redirect=pricing&plan=pro&duration=${duration}`;
      return;
    }

    if (loading) return;
    setLoading(true);
    setError(null);

    try {
      const res = await fetch("/api/subscription/checkout", {
        method: "POST",
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ plan_id: "pro", duration }),
      });

      const data = await res.json().catch(() => null);
      const checkoutUrl = data?.data?.checkout_url ?? data?.data?.redirect_url;

      if (res.ok && checkoutUrl) {
        window.location.href = checkoutUrl;
        return;
      }
      setError(data?.error?.message || (locale === "en" ? "Failed to create checkout session. Please try again." : "Gagal membuat sesi checkout. Silakan coba lagi."));
    } catch {
      setError(locale === "en" ? "An error occurred. Please try again." : "Terjadi kesalahan. Silakan coba lagi.");
    } finally {
      setLoading(false);
    }
  }

  // Auto-checkout after returning from login (e.g. /?plan=pro&checkout=true#pricing).
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const plan = params.get("plan");
    const dur = params.get("duration");
    const auto = params.get("checkout") === "true";

    if (dur) {
      const idx = DURATIONS.findIndex((d) => d.key === dur);
      if (idx >= 0) setBilling(idx);
    }

    if (plan === "pro" && auto && localStorage.getItem("token")) {
      const hash = window.location.hash || "";
      window.history.replaceState({}, document.title, window.location.pathname + hash);
      setTimeout(() => startProCheckout(dur ?? undefined), 400);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <section id="pricing" className="py-24">
      <Container>
        <div className="text-center max-w-[720px] mx-auto mb-14 reveal">
          <Eyebrow className="mb-[18px]">
            <SparkleIcon /> {t.pricing.eyebrow}
          </Eyebrow>
          <h2 className="font-extrabold leading-[1.05] -tracking-[0.03em] text-[clamp(32px,4vw,52px)]">
            {t.pricing.title}
          </h2>
          <p className="mt-3.5 text-ink-500 font-medium leading-[1.5] text-[clamp(16px,1.4vw,19px)]">{t.pricing.subtitle}</p>
          <div className="inline-flex mt-3.5 p-1 bg-ink-100 rounded-full relative">
            <div
              ref={indicatorRef}
              className="absolute top-1 bottom-1 bg-white rounded-full shadow-[0_2px_6px_rgba(0,0,0,0.08)] z-[1] transition-[left,width] duration-[400ms]"
              style={{ transitionTimingFunction: "var(--ease-spring)" }}
            />
            {DURATIONS.map((o, i) => {
              const disc = PLANS.pro?.durations?.[o.key]?.discount ?? 0;
              return (
                <button
                  key={o.key}
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
                  {disc > 0 && (
                    <span className="text-[10px] bg-purple-100 text-purple-700 py-0.5 px-1.5 rounded font-bold">-{disc}%</span>
                  )}
                </button>
              );
            })}
          </div>
        </div>

        {error && (
          <div className="max-w-[880px] mx-auto mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-center text-sm font-medium text-red-700">
            {error}
          </div>
        )}

        <div className="grid sm:grid-cols-2 gap-5 max-w-[880px] mx-auto">
          {/* Basic */}
          <div className="reveal bg-white border border-ink-100 rounded-[28px] p-8 transition-all hover:-translate-y-1 hover:shadow-card relative">
            <h3 className="text-[22px] font-bold -tracking-[0.02em]">{t.pricing.basicName}</h3>
            <div className="text-[13.5px] text-ink-500 mt-1.5">{t.pricing.basicTag}</div>
            <div className="my-[22px] flex items-baseline gap-1.5">
              <span className="text-[44px] font-extrabold -tracking-[0.035em] leading-none">Rp0</span>
              <span className="text-sm text-ink-500 font-medium">{t.pricing.basicPriceSuffix}</span>
            </div>
            <ul className="list-none p-0 m-0 grid gap-2.5">
              {t.pricing.basicFeatures.map((f) => (
                <li key={f} className="flex items-center gap-2.5 text-sm">
                  <span className="inline-grid place-items-center w-[18px] h-[18px] rounded-full bg-purple-100 text-purple-700 flex-shrink-0">
                    <CheckIcon />
                  </span>
                  {f}
                </li>
              ))}
            </ul>
            <a
              href="/register"
              className="mt-6 inline-flex items-center justify-center w-full gap-2 h-12 px-[22px] rounded-full font-semibold text-[15px] bg-white text-ink-900 border border-ink-200 hover:bg-ink-50 hover:border-ink-300 transition-all"
            >
              {t.pricing.basicCta}
            </a>
          </div>

          {/* Pro */}
          <div className="reveal reveal-delay-2 bg-gradient-to-b from-[#1B1430] to-[#2D1B5C] text-white rounded-[28px] p-8 transition-all hover:-translate-y-1 shadow-purple relative">
            <span className="absolute top-6 right-6 text-[10px] font-extrabold tracking-[0.08em] py-1 px-2.5 rounded-full bg-purple-500 uppercase">
              {t.pricing.popular}
            </span>
            <h3 className="text-[22px] font-bold -tracking-[0.02em]">{t.pricing.proName}</h3>
            <div className="text-[13.5px] text-white/60 mt-1.5">{t.pricing.proTag}</div>
            <div className="my-[22px]">
              <div className="flex items-baseline gap-1.5">
                <span className="text-[44px] font-extrabold -tracking-[0.035em] leading-none">{rupiah(proTotal)}</span>
                <span className="text-sm text-white/60 font-medium">{DURATIONS[billing].suffix}</span>
              </div>
              {billing !== 0 && (
                <div className="mt-1.5 text-[13px] text-white/60 font-medium">{rupiah(proPerMonth)}{perMonthSuffix}</div>
              )}
            </div>
            <ul className="list-none p-0 m-0 grid gap-2.5">
              {t.pricing.proFeatures.map((f) => (
                <li key={f} className="flex items-center gap-2.5 text-sm text-white/85">
                  <span className="inline-grid place-items-center w-[18px] h-[18px] rounded-full bg-purple-600/30 text-white flex-shrink-0">
                    <CheckIcon />
                  </span>
                  {f}
                </li>
              ))}
            </ul>
            <button
              type="button"
              onClick={() => startProCheckout()}
              disabled={loading}
              className="mt-6 inline-flex items-center justify-center w-full gap-2 h-12 px-[22px] rounded-full font-semibold text-[15px] bg-white text-purple-700 hover:-translate-y-px transition-all disabled:opacity-60 disabled:cursor-not-allowed disabled:translate-y-0"
            >
              {loading ? (locale === "en" ? "Processing…" : "Memproses…") : <>{t.pricing.proCta} <ArrowIcon /></>}
            </button>
          </div>
        </div>
      </Container>
    </section>
  );
}
