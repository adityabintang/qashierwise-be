import type { ComponentType, SVGProps } from "react";
import { AiIcon, CalendarIcon, CardIcon, ChartIcon, InboxIcon, TruckIcon } from "./icons";
import { Container, SectionHead } from "./section-shared";

type FeatureItem = {
  Icon: ComponentType<SVGProps<SVGSVGElement>>;
  title: string;
  desc: string;
};

const features: FeatureItem[] = [
  { Icon: AiIcon, title: "AI Chatbot WhatsApp", desc: "Chatbot dengan model AI, terima order, jawab ke staff, handover ke live agent kapan saja." },
  { Icon: InboxIcon, title: "Console Satu Layar", desc: "Inbox pesan, pesanan, status reservasi, dan semuanya dalam satu dashboard." },
  { Icon: TruckIcon, title: "Order Delivery & Pickup", desc: "Penjadwalan, order & delivery rute terdekat, pembayaran QRIS terintegrasi." },
  { Icon: CalendarIcon, title: "Reservasi Pintar", desc: "Slot ketersediaan, jam buka, kapasitas, dan notifikasi staff. Penjagaan T-24h & T-1hr." },
  { Icon: CardIcon, title: "Pembayaran QRIS", desc: "Kirim invoice QRIS atau terima QR statis via chat, semua tercatat otomatis." },
  { Icon: ChartIcon, title: "Laporan & CRM", desc: "CRM, repeat rate, tag pelanggan, dan export CSV (Pro)." },
];

export function Features() {
  return (
    <section id="features" className="pt-10 pb-16">
      <Container>
        <SectionHead
          eyebrowText="Fitur"
          title="Fitur utama yang restoran butuhkan"
          subtitle="Semua alat & aplikasi dibuat untuk memudahkan pemasukan restoran."
        />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-[18px]">
          {features.map((f, i) => (
            <div
              key={f.title}
              className={`reveal reveal-delay-${(i % 3) + 1} bg-white border border-ink-100 rounded-[28px] p-[26px] transition-all duration-[400ms] hover:-translate-y-1 hover:shadow-card hover:border-purple-100 relative overflow-hidden`}
            >
              <div className="w-11 h-11 rounded-xl bg-purple-50 text-purple-700 grid place-items-center mb-4">
                <f.Icon />
              </div>
              <h3 className="text-[17px] font-bold">{f.title}</h3>
              <p className="mt-2 text-ink-500 text-[14.5px] leading-[1.5]">{f.desc}</p>
            </div>
          ))}
        </div>
      </Container>
    </section>
  );
}
