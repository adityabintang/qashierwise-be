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
          <span dangerouslySetInnerHTML={{ __html: item }} />
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

export default function RefundPolicyPage() {
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
            Kebijakan Pengembalian Dana
          </h1>
          <p className="mt-3 text-white/70 text-sm">
            Terakhir diperbarui: 23 Januari 2026
          </p>
        </div>
      </div>

      <div className="max-w-3xl mx-auto px-5 py-14">
        <p className="text-ink-600 leading-relaxed mb-10">
          Di QashierWise, kami berkomitmen untuk memberikan layanan terbaik
          kepada pelanggan kami. Kebijakan pengembalian dana ini menjelaskan hak
          Anda terkait pengembalian dana untuk layanan berlangganan QashierWise.
        </p>

        <Section number={1} title="Periode Uji Coba Gratis">
          <p className="text-ink-600 leading-relaxed">
            QashierWise menawarkan periode uji coba gratis selama 14 hari untuk
            pengguna baru:
          </p>
          <BulletList
            items={[
              "Tidak ada biaya yang dikenakan selama periode uji coba",
              "Anda dapat membatalkan kapan saja selama periode uji coba tanpa dikenakan biaya",
              "Setelah periode uji coba berakhir, langganan akan otomatis dikonversi ke paket berbayar yang dipilih",
              "Anda akan menerima notifikasi sebelum periode uji coba berakhir",
            ]}
          />
        </Section>

        <Section number={2} title="Kebijakan Pengembalian Dana 7 Hari">
          <p className="text-ink-600 leading-relaxed">
            Kami menawarkan jaminan pengembalian dana 100% dalam 7 hari pertama
            setelah pembayaran pertama Anda:
          </p>
          <BulletList
            items={[
              "Berlaku untuk pembayaran pertama paket Standard dan Pro",
              "Permintaan pengembalian dana harus diajukan dalam 7 hari kalender sejak tanggal pembayaran",
              "Pengembalian dana akan diproses ke metode pembayaran asli",
              "Waktu pemrosesan pengembalian dana: 5-14 hari kerja tergantung penyedia pembayaran",
              "Akun Anda akan dinonaktifkan setelah pengembalian dana diproses",
            ]}
          />
        </Section>

        <Section number={3} title="Pembatalan Langganan">
          <p className="text-ink-600 leading-relaxed">
            Anda dapat membatalkan langganan Anda kapan saja:
          </p>
          <BulletList
            items={[
              "Pembatalan dapat dilakukan melalui dashboard akun Anda",
              "Layanan akan tetap aktif hingga akhir periode billing yang telah dibayar",
              "Tidak ada pengembalian dana prorata untuk pembatalan di tengah periode billing (setelah 7 hari pertama)",
              "Data Anda akan disimpan selama 30 hari setelah pembatalan untuk memudahkan reaktivasi",
              "Setelah 30 hari, data akan dihapus secara permanen sesuai kebijakan privasi kami",
            ]}
          />
        </Section>

        <Section number={4} title="Kondisi yang Tidak Memenuhi Syarat Pengembalian Dana">
          <p className="text-ink-600 leading-relaxed">
            Pengembalian dana tidak akan diberikan dalam kondisi berikut:
          </p>
          <BulletList
            items={[
              "Permintaan pengembalian dana diajukan setelah 7 hari sejak pembayaran pertama",
              "Pelanggaran terhadap Ketentuan Layanan kami",
              "Penyalahgunaan layanan atau aktivitas penipuan",
              "Pembayaran perpanjangan langganan (hanya pembayaran pertama yang memenuhi syarat)",
              "Biaya transaksi QRIS atau payment gateway yang dikenakan oleh pihak ketiga",
              "Downgrade dari paket Pro ke Standard atau Basic",
            ]}
          />
        </Section>

        <Section number={5} title="Cara Mengajukan Pengembalian Dana">
          <p className="text-ink-600 leading-relaxed mb-4">
            Untuk mengajukan pengembalian dana, ikuti langkah-langkah berikut:
          </p>
          <ol className="grid gap-4">
            {[
              <>
                Hubungi tim support kami melalui email di{" "}
                <a
                  href="mailto:support@qashierwise.com"
                  className="text-purple-700 hover:underline"
                >
                  support@qashierwise.com
                </a>{" "}
                atau WhatsApp di{" "}
                <a
                  href="https://wa.me/62882003235019"
                  className="text-purple-700 hover:underline"
                >
                  +62882003235019
                </a>
              </>,
              <>
                Sertakan informasi berikut:
                <BulletList
                  items={[
                    "Nama akun dan email terdaftar",
                    "Nomor invoice atau ID transaksi",
                    "Alasan permintaan pengembalian dana",
                    "Tanggal pembayaran",
                  ]}
                />
              </>,
              "Tim kami akan meninjau permintaan Anda dalam 2-3 hari kerja",
              "Jika disetujui, pengembalian dana akan diproses dalam 5-14 hari kerja",
              "Anda akan menerima konfirmasi email setelah pengembalian dana diproses",
            ].map((step, i) => (
              <li key={i} className="flex gap-3 items-start">
                <span className="shrink-0 w-6 h-6 rounded-full bg-purple-100 text-purple-700 text-xs font-bold grid place-items-center mt-0.5">
                  {i + 1}
                </span>
                <div className="text-ink-600">{step}</div>
              </li>
            ))}
          </ol>
        </Section>

        <Section number={6} title="Pengembalian Dana untuk Transaksi QRIS">
          <p className="text-ink-600 leading-relaxed">
            Untuk transaksi QRIS yang dilakukan oleh pelanggan restoran Anda:
          </p>
          <BulletList
            items={[
              "QashierWise hanya menyediakan platform, bukan penyedia payment gateway",
              "Pengembalian dana transaksi QRIS diatur oleh kebijakan penyedia payment gateway Anda (Midtrans, Xendit, dll)",
              "Anda bertanggung jawab untuk mengelola pengembalian dana kepada pelanggan Anda",
              "QashierWise tidak bertanggung jawab atas sengketa transaksi antara Anda dan pelanggan Anda",
              "Biaya transaksi yang dikenakan oleh payment gateway tidak dapat dikembalikan",
            ]}
          />
        </Section>

        <Section number={7} title="Upgrade dan Downgrade Paket">
          <p className="text-ink-600 leading-relaxed mb-3">
            Ketentuan untuk perubahan paket langganan:
          </p>
          <div className="grid gap-3 mb-3">
            <p
              className="text-ink-600"
              dangerouslySetInnerHTML={{
                __html:
                  "<strong>Upgrade:</strong> Perbedaan harga akan diprorata dan ditagih segera. Fitur baru akan aktif setelah pembayaran berhasil",
              }}
            />
            <p
              className="text-ink-600"
              dangerouslySetInnerHTML={{
                __html:
                  "<strong>Downgrade:</strong> Perubahan akan berlaku pada periode billing berikutnya. Tidak ada pengembalian dana untuk perbedaan harga",
              }}
            />
          </div>
          <BulletList
            items={[
              "Anda dapat mengubah paket kapan saja melalui dashboard akun",
              "Fitur yang tidak tersedia di paket baru akan dinonaktifkan setelah downgrade",
            ]}
          />
        </Section>

        <Section number={8} title="Gangguan Layanan dan Kompensasi">
          <p className="text-ink-600 leading-relaxed">
            Dalam hal terjadi gangguan layanan yang signifikan:
          </p>
          <BulletList
            items={[
              "Kami akan memberikan notifikasi tentang gangguan layanan melalui email atau dashboard",
              "Jika gangguan berlangsung lebih dari 24 jam berturut-turut, Anda berhak mendapatkan kredit layanan prorata",
              "Kredit layanan akan otomatis diterapkan ke periode billing berikutnya",
              "Gangguan yang disebabkan oleh pemeliharaan terjadwal tidak memenuhi syarat untuk kompensasi",
              "Force majeure (bencana alam, perang, dll) tidak termasuk dalam kebijakan kompensasi",
            ]}
          />
        </Section>

        <Section number={9} title="Perubahan Kebijakan">
          <p className="text-ink-600 leading-relaxed">
            QashierWise berhak untuk mengubah kebijakan pengembalian dana ini
            kapan saja. Perubahan akan:
          </p>
          <BulletList
            items={[
              'Diposting di halaman ini dengan tanggal "terakhir diperbarui" yang baru',
              "Diberitahukan kepada pengguna aktif melalui email",
              "Berlaku untuk transaksi baru setelah tanggal perubahan",
              "Tidak mempengaruhi hak pengembalian dana yang sudah ada sebelum perubahan",
            ]}
          />
        </Section>

        <Section number={10} title="Hubungi Kami">
          <p className="text-ink-600 leading-relaxed mb-5">
            Jika Anda memiliki pertanyaan tentang kebijakan pengembalian dana ini
            atau ingin mengajukan permintaan pengembalian dana, silakan hubungi
            kami:
          </p>
          <div className="rounded-2xl bg-purple-50 border border-purple-100 p-6">
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
                  href="https://wa.me/62882003235019"
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

        <div className="rounded-2xl border-2 border-amber-200 bg-amber-50 p-6 mt-4">
          <h3 className="font-bold text-amber-900 mb-2">Catatan Penting</h3>
          <p className="text-amber-800 text-sm leading-relaxed">
            Dengan menggunakan layanan QashierWise, Anda menyetujui kebijakan
            pengembalian dana ini. Kami sangat menyarankan Anda untuk
            memanfaatkan periode uji coba gratis 14 hari untuk memastikan
            layanan kami sesuai dengan kebutuhan bisnis Anda sebelum melakukan
            pembayaran.
          </p>
        </div>
      </div>
    </LegalLayout>
  );
}
