import { AiIcon, QrIcon, UsersIcon, WhatsAppIcon } from "./icons";
import { Container, SectionHead } from "./section-shared";

const steps = [
  {
    n: "01",
    title: "Terima chat di WhatsApp",
    desc: "Pelanggan kirim chat ke nomor restoran Anda — tanpa app baru, tanpa training.",
    Icon: WhatsAppIcon,
    color: "from-[#5BD577] to-ios-green",
  },
  {
    n: "02",
    title: "AI merespons otomatis",
    desc: "AI menjawab menu, jam buka, reservasi, dan pertanyaan umum dalam hitungan detik.",
    Icon: AiIcon,
    color: "from-purple-400 to-purple-700",
  },
  {
    n: "03",
    title: "Bayar dengan QRIS",
    desc: "Kirim invoice QRIS otomatis — Midtrans, Xendit, atau OkeOce. Status terdeteksi instan.",
    Icon: QrIcon,
    color: "from-[#5AAEFF] to-ios-blue",
  },
  {
    n: "04",
    title: "Pantau di satu Console",
    desc: "Lihat semua pesanan, reservasi & laporan dalam satu dashboard yang tenang.",
    Icon: UsersIcon,
    color: "from-[#FFB85C] to-ios-orange",
  },
];

export function HowItWorks() {
  return (
    <section id="how" className="pt-10 pb-24">
      <Container>
        <SectionHead
          eyebrowText="Cara Kerja"
          title="Otomatis dengan AI + QRIS"
          subtitle="Semuanya untuk bisnis restoran: cepat, ringkas, dan akurat."
        />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {steps.map((s, i) => (
            <div
              key={s.n}
              className={`reveal reveal-delay-${i + 1} bg-white border border-ink-100 rounded-[28px] p-6 transition-all duration-[400ms] hover:-translate-y-1 hover:shadow-card hover:border-ink-200 relative overflow-hidden`}
            >
              <div className="text-xs font-bold text-ink-400 tracking-[0.08em]">{s.n}</div>
              <div
                className={`w-12 h-12 rounded-[14px] grid place-items-center my-4 text-white bg-gradient-to-br ${s.color} shadow-[0_6px_14px_rgba(0,0,0,0.1),inset_0_1px_0_rgba(255,255,255,0.25)]`}
              >
                <s.Icon />
              </div>
              <h3 className="text-[17px] font-bold -tracking-[0.015em]">{s.title}</h3>
              <p className="mt-1.5 text-sm leading-[1.5] text-ink-500">{s.desc}</p>
            </div>
          ))}
        </div>
      </Container>
    </section>
  );
}
