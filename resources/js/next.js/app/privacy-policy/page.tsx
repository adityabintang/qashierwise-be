import { LegalLayout } from "@/app/_legal/legal-layout";



const CONTACT = {
  email: "support@qashierwise.com",
  wa: "+62882003235019",
  address: "Jl. Widosari No. 55, Tegalrejo Raya, Salatiga, Jawa Tengah, Indonesia 50733",
  hours: "Senin - Jumat, 09:00 - 17:00 WIB",
};

function BulletList({ items }: { items: string[] }) {
  return (
    <ul className="mt-3 grid gap-2.5">
      {items.map((item, i) => (
        <li key={i} className="flex gap-2.5 items-start text-ink-600">
          <span className="mt-[7px] shrink-0 w-1.5 h-1.5 rounded-full bg-purple-500" />
          <span>{item}</span>
        </li>
      ))}
    </ul>
  );
}

function Section({
  number,
  title,
  children,
}: {
  number: number;
  title: string;
  children: React.ReactNode;
}) {
  return (
    <section className="mb-10">
      <h2 className="text-[19px] font-bold text-ink-900 mb-3">
        {number}. {title}
      </h2>
      {children}
    </section>
  );
}

export default function PrivacyPolicyPage() {
  return (
    <LegalLayout>
      <div className="bg-gradient-to-br from-purple-700 to-purple-900 text-white pt-16 pb-14 px-5">
        <div className="max-w-3xl mx-auto">
          <a
            href="/"
            className="inline-flex items-center gap-1.5 text-white/70 hover:text-white text-sm mb-6 transition-colors"
          >
            ← Kembali ke Beranda
          </a>
          <h1 className="text-[clamp(28px,4vw,40px)] font-extrabold -tracking-[0.03em] leading-[1.1]">
            Kebijakan Privasi
          </h1>
          <p className="mt-3 text-white/70 text-sm">
            Terakhir diperbarui: 27 November 2025
          </p>
        </div>
      </div>

      <div className="max-w-3xl mx-auto px-5 py-14">
        <Section number={1} title="Informasi yang Kami Kumpulkan">
          <p className="text-ink-600 leading-relaxed">
            QashierWise mengumpulkan informasi yang Anda berikan kepada kami
            ketika menggunakan layanan kami, termasuk:
          </p>
          <BulletList
            items={[
              "Informasi bisnis restoran (nama, alamat, kontak)",
              "Data reservasi dan pesanan pelanggan",
              "Informasi komunikasi melalui WhatsApp Business API",
              "Data transaksi dan pembayaran",
              "Informasi penggunaan layanan dan statistik",
            ]}
          />
        </Section>

        <Section number={2} title="Penggunaan WhatsApp Business API">
          <p className="text-ink-600 leading-relaxed">
            QashierWise menggunakan WhatsApp Business API untuk memfasilitasi
            komunikasi antara restoran dan pelanggan. Kami:
          </p>
          <BulletList
            items={[
              "Memproses pesan reservasi dan pesanan melalui WhatsApp",
              "Menyimpan riwayat percakapan untuk keperluan operasional",
              "Menggunakan data untuk meningkatkan layanan chatbot AI",
              "Tidak membagikan data WhatsApp Anda kepada pihak ketiga tanpa izin",
              "Mematuhi kebijakan privasi WhatsApp dan Meta",
            ]}
          />
        </Section>

        <Section number={3} title="Bagaimana Kami Menggunakan Informasi">
          <p className="text-ink-600 leading-relaxed">
            Informasi yang dikumpulkan digunakan untuk:
          </p>
          <BulletList
            items={[
              "Menyediakan dan meningkatkan layanan QashierWise",
              "Memproses reservasi dan pesanan pelanggan",
              "Mengirim notifikasi dan konfirmasi melalui WhatsApp",
              "Menganalisis dan meningkatkan performa sistem",
              "Mematuhi kewajiban hukum dan peraturan yang berlaku",
            ]}
          />
        </Section>

        <Section number={4} title="Keamanan Data">
          <p className="text-ink-600 leading-relaxed">
            Kami menerapkan langkah-langkah keamanan yang sesuai untuk
            melindungi informasi Anda dari akses, pengungkapan, perubahan, atau
            penghancuran yang tidak sah. Data disimpan dengan enkripsi dan hanya
            dapat diakses oleh personel yang berwenang.
          </p>
        </Section>

        <Section number={5} title="Pembagian Informasi">
          <p className="text-ink-600 leading-relaxed">
            Kami tidak menjual, menyewakan, atau membagikan informasi pribadi
            Anda kepada pihak ketiga, kecuali:
          </p>
          <BulletList
            items={[
              "Dengan persetujuan Anda",
              "Untuk mematuhi kewajiban hukum",
              "Dengan penyedia layanan pihak ketiga yang membantu operasional kami (seperti WhatsApp Business API, payment gateway)",
              "Untuk melindungi hak, properti, atau keamanan QashierWise dan pengguna kami",
            ]}
          />
        </Section>

        <Section number={6} title="Retensi Data">
          <p className="text-ink-600 leading-relaxed">
            Kami menyimpan informasi Anda selama akun Anda aktif atau sepanjang
            diperlukan untuk menyediakan layanan. Anda dapat meminta penghapusan
            data dengan menghubungi kami.
          </p>
        </Section>

        <Section number={7} title="Hak Anda">
          <p className="text-ink-600 leading-relaxed">
            Anda memiliki hak untuk:
          </p>
          <BulletList
            items={[
              "Mengakses dan mendapatkan salinan data pribadi Anda",
              "Memperbaiki data yang tidak akurat",
              "Meminta penghapusan data Anda",
              "Membatasi atau menolak pemrosesan data tertentu",
              "Menarik persetujuan yang telah diberikan",
            ]}
          />
        </Section>

        <Section number={8} title="Cookies dan Teknologi Pelacakan">
          <p className="text-ink-600 leading-relaxed">
            Website kami menggunakan cookies dan teknologi serupa untuk
            meningkatkan pengalaman pengguna, menganalisis traffic, dan
            personalisasi konten. Anda dapat mengatur preferensi cookies melalui
            browser Anda.
          </p>
        </Section>

        <Section number={9} title="Perubahan Kebijakan Privasi">
          <p className="text-ink-600 leading-relaxed">
            Kami dapat memperbarui kebijakan privasi ini dari waktu ke waktu.
            Perubahan akan diposting di halaman ini dengan tanggal "terakhir
            diperbarui" yang baru. Kami mendorong Anda untuk meninjau kebijakan
            ini secara berkala.
          </p>
        </Section>

        <Section number={10} title="Hubungi Kami">
          <p className="text-ink-600 leading-relaxed">
            Jika Anda memiliki pertanyaan tentang kebijakan privasi ini atau
            ingin menggunakan hak privasi Anda, silakan hubungi kami:
          </p>
          <div className="mt-5 rounded-2xl bg-purple-50 border border-purple-100 p-6">
            <div className="grid gap-3 text-sm">
              <div>
                <span className="font-semibold text-ink-900">Email: </span>
                <a
                  href={`mailto:${CONTACT.email}`}
                  className="text-purple-700 hover:underline"
                >
                  {CONTACT.email}
                </a>
              </div>
              <div>
                <span className="font-semibold text-ink-900">WhatsApp: </span>
                <a
                  href={`https://wa.me/62882003235019`}
                  className="text-purple-700 hover:underline"
                >
                  {CONTACT.wa}
                </a>
              </div>
              <div>
                <span className="font-semibold text-ink-900">Alamat: </span>
                <span className="text-ink-600">{CONTACT.address}</span>
              </div>
              <div>
                <span className="font-semibold text-ink-900">
                  Jam Operasional:{" "}
                </span>
                <span className="text-ink-600">{CONTACT.hours}</span>
              </div>
            </div>
          </div>
        </Section>

        <Section number={11} title="Kepatuhan terhadap Regulasi">
          <p className="text-ink-600 leading-relaxed">
            QashierWise berkomitmen untuk mematuhi peraturan perlindungan data
            yang berlaku di Indonesia, termasuk Undang-Undang Perlindungan Data
            Pribadi (UU PDP), serta kebijakan WhatsApp Business API dan Meta
            Platform.
          </p>
        </Section>
      </div>
    </LegalLayout>
  );
}
