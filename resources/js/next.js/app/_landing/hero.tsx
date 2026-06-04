import { ArrowIcon, CheckIcon, ChartIcon, WhatsAppIcon } from "./icons";
import { ChatDemo } from "./chat-demo";
import { DemoVideoButton } from "./demo-video-button";

const bullets = [
  "Reservasi & Order via WhatsApp tanpa ribet",
  "Mulai pesanan + laporan dengan mudah",
  "Tanpa pelatihan workflow (bayar di tempat)",
  "Laporan Order & penjualan otomatis",
];

export function Hero() {
  return (
    <section className="relative pt-[72px] pb-16 overflow-hidden">
      <div className="hero-bg" />
      <div className="relative z-[1] max-w-[1200px] mx-auto px-5 sm:px-7">
        <div className="grid lg:grid-cols-[1.1fr_1fr] gap-10 lg:gap-[60px] items-center">
          <div className="max-w-[560px]">
            <div className="reveal inline-flex items-center gap-2 text-[13px] font-semibold tracking-wider text-purple-700 bg-purple-50 px-3 py-1.5 rounded-full border border-purple-100">
              <span className="w-1.5 h-1.5 rounded-full bg-purple-600 animate-pulse-dot" />
              Didukung AI · Terintegrasi QRIS
            </div>
            <h1 className="reveal reveal-delay-1 mt-[18px] font-extrabold leading-[1.02] -tracking-[0.035em] text-[clamp(40px,6vw,76px)]">
              Respon lebih&nbsp;cepat,
              <br />
              jual lebih <span className="text-grad">banyak</span>.
            </h1>
            <p className="reveal reveal-delay-2 mt-[22px] max-w-[480px] text-ink-500 font-medium leading-[1.5] text-[clamp(16px,1.4vw,19px)]">
              QashierWise menghadirkan Chatbot WhatsApp berbasis AI untuk restoran — dengan
              satu layar admin: Inbox, Pesanan, Reservasi, Menu, dan CRM.
            </p>
            <div className="reveal reveal-delay-3 mt-8 flex gap-2.5 flex-wrap">
              <a
                href="/login"
                className="inline-flex items-center justify-center gap-2 h-12 px-[22px] rounded-full font-semibold text-[15px] bg-purple-600 text-white shadow-purple hover:bg-purple-700 hover:-translate-y-px transition-all"
              >
                Coba Gratis 14 Hari <ArrowIcon />
              </a>
              <DemoVideoButton />
            </div>
            <div className="reveal reveal-delay-4 mt-7 grid gap-2.5 text-sm text-ink-500">
              {bullets.map((t) => (
                <div key={t} className="flex items-center gap-2.5">
                  <span className="inline-grid place-items-center w-[18px] h-[18px] rounded-full bg-purple-100 text-purple-700 flex-shrink-0">
                    <CheckIcon />
                  </span>
                  {t}
                </div>
              ))}
            </div>
          </div>

          <div className="reveal reveal-delay-2 relative flex justify-center">
            <div className="absolute top-14 right-3 lg:-right-4 bg-white rounded-[18px] py-3 px-4 shadow-card flex items-center gap-2.5 text-[13px] font-semibold z-10">
              <div className="w-7 h-7 rounded-lg grid place-items-center flex-shrink-0 bg-purple-100 text-purple-700">
                <ChartIcon />
              </div>
              <div>
                <div className="text-[11px] text-ink-500 font-medium">Penjualan</div>
                <div className="flex items-center gap-1.5">
                  <span>+38%</span>
                  <span className="inline-flex items-end gap-px h-4 ml-1 text-ios-green">
                    <i className="block w-[3px] bg-current rounded-px h-[40%] animate-spark-pulse" />
                    <i className="block w-[3px] bg-current rounded-px h-[70%] animate-spark-pulse [animation-delay:0.2s]" />
                    <i className="block w-[3px] bg-current rounded-px h-[50%] animate-spark-pulse [animation-delay:0.4s]" />
                    <i className="block w-[3px] bg-current rounded-px h-[90%] animate-spark-pulse [animation-delay:0.6s]" />
                  </span>
                </div>
              </div>
            </div>
            <div className="absolute bottom-20 left-3 lg:-left-8 bg-white rounded-[18px] py-3 px-4 shadow-card flex items-center gap-2.5 text-[13px] font-semibold z-10">
              <div className="w-7 h-7 rounded-lg grid place-items-center flex-shrink-0 bg-[#E7F8EC] text-ios-green">
                <WhatsAppIcon />
              </div>
              <div>
                <div className="text-[11px] text-ink-500 font-medium">Pesan Masuk</div>
                <div>4 baru</div>
              </div>
            </div>
            <div className="w-[320px] h-[640px] bg-ink-900 rounded-[52px] p-2.5 shadow-elevated relative">
              <ChatDemo />
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
