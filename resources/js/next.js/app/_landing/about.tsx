import { Container, SectionHead } from "./section-shared";

export function About() {
  return (
    <section id="about" className="pt-10 pb-24">
      <Container>
        <SectionHead
          eyebrowText="Tentang"
          title="Kenali lebih dekat QashierWise"
          subtitle="Misi kami: bikin restoran Indonesia bisa fokus pada pelanggan — bukan operasional."
        />
        <div className="reveal bg-gradient-to-br from-purple-50 to-white border border-purple-100 rounded-[28px] p-8 grid sm:grid-cols-[auto_1fr] gap-6 items-center max-w-[880px] mx-auto text-center sm:text-left justify-items-center sm:justify-items-stretch">
          <div className="w-[88px] h-[88px] rounded-full bg-gradient-to-br from-purple-500 to-purple-800 text-white grid place-items-center text-3xl font-extrabold shadow-[0_8px_22px_rgba(124,58,237,0.3)]">
            AB
          </div>
          <div>
            <div className="text-[11px] font-bold tracking-[0.1em] uppercase text-purple-700">Founder</div>
            <div className="text-[22px] font-extrabold mt-1">Aditya Bintang Fadila</div>
            <p className="text-ink-500 mt-1.5 text-[14.5px] leading-[1.5] max-w-[560px]">
              QashierWise dibangun atas keyakinan bahwa restoran Indonesia berhak punya teknologi yang
              terasa ringan, modern, dan terjangkau — sama seperti aplikasi yang Anda pakai sehari-hari.
            </p>
          </div>
        </div>
      </Container>
    </section>
  );
}
