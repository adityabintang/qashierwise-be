import { LegalLayout } from "@/app/_legal/legal-layout";



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
  label,
  title,
  children,
}: {
  label: string;
  title: string;
  children: React.ReactNode;
}) {
  return (
    <section className="mb-10">
      <h2 className="text-[19px] font-bold text-ink-900 mb-3">
        {label}. {title}
      </h2>
      {children}
    </section>
  );
}

function Subsection({
  label,
  title,
  children,
}: {
  label: string;
  title: string;
  children: React.ReactNode;
}) {
  return (
    <div className="mb-6">
      <h3 className="text-base font-semibold text-ink-900 mb-2">
        {label}. {title}
      </h3>
      {children}
    </div>
  );
}

export default function TermsOfServicePage() {
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
            Ketentuan Layanan
          </h1>
          <p className="mt-3 text-white/70 text-sm">
            Terakhir diperbarui: 10 Februari 2025
          </p>
        </div>
      </div>

      <div className="max-w-3xl mx-auto px-5 py-14">
        <div className="mb-10 p-6 rounded-2xl bg-ink-50 border border-ink-100">
          <h2 className="text-[19px] font-bold text-ink-900 mb-4">
            Syarat dan Ketentuan Penggunaan Produk QashierWise
          </h2>
          <p className="text-ink-600 leading-relaxed mb-4">
            Terima kasih atas kepercayaan Anda menggunakan produk QashierWise.
            Dengan menggunakan layanan dan/atau produk yang disediakan oleh
            "QashierWise", Anda, perusahaan dan/atau bisnis yang telah
            memberikan izin atau otorisasi untuk mewakili Anda ("Pengguna")
            setuju dengan Syarat dan Ketentuan Penggunaan Produk QashierWise
            berikut ini serta syarat, kebijakan dan dokumentasi terkait lainnya
            yang disediakan oleh QashierWise dari waktu ke waktu ("Syarat dan
            Ketentuan").
          </p>
          <p className="text-ink-600 leading-relaxed">
            QashierWise dapat meninjau dan mengubah Syarat dan Ketentuan ini
            dari waktu ke waktu atas kebijakan QashierWise sendiri. Pengguna
            mengakui dan menyetujui bahwa Pengguna wajib memantau Syarat dan
            Ketentuan ini dari waktu ke waktu untuk mengetahui kondisi atau
            informasi terbaru mengenai ketentuan penggunaan Produk yang
            disediakan oleh QashierWise.
          </p>
        </div>

        <Section label="I" title="Ketentuan Umum">
          <p className="text-ink-600 leading-relaxed mb-5">
            Ketentuan Umum ini berlaku untuk semua Pengguna yang menggunakan
            Produk (sebagaimana didefinisikan di bawah) yang disediakan oleh
            QashierWise.
          </p>

          <Subsection label="A" title="Definisi Umum">
            <BulletList
              items={[
                "<strong>BAST</strong> berarti Berita Acara Serah Terima, dokumen yang ditandatangani oleh Para Pihak sebelum Pelatihan dilakukan yang menyatakan bahwa Produk dapat digunakan oleh Pengguna.",
                "<strong>Hak Kekayaan Intelektual</strong> berarti semua paten, hak cipta, merek dagang, dan hak terkait lainnya sebagaimana didefinisikan oleh hukum yang berlaku.",
                "<strong>Informasi Rahasia</strong> berarti semua informasi terkait bisnis QashierWise yang telah atau akan diberikan kepada Pengguna termasuk namun tidak terbatas pada desain produk, informasi keuangan, dan rencana pemasaran.",
                "<strong>Layanan Percobaan</strong> berarti layanan Produk yang diberikan kepada Pengguna dengan batasan tertentu sebagaimana dimaksudkan ini.",
                "<strong>Periode Aktif</strong> berarti periode aktif langganan Produk berdasarkan paket yang dibayar oleh Pengguna.",
                "<strong>Keadaan Kahar</strong> berarti kondisi di luar kendali yang tidak terduga yang menyebabkan Para Pihak untuk memenuhi kewajiban mereka berdasarkan Syarat dan Ketentuan ini.",
                "<strong>Perjanjian Pengguna</strong> berarti perjanjian penggunaan Produk yang ditandatangani oleh Para Pihak yang merinci ketentuan penggunaan Produk.",
                "<strong>Pihak</strong> berarti QashierWise atau Pengguna.",
                "<strong>Produk</strong> berarti produk yang ditawarkan dan disediakan oleh QashierWise, termasuk namun tidak terbatas pada Sistem POS, Manajemen Inventaris, Laporan Analitik, dan Integrasi Pembayaran.",
                "<strong>Quotation</strong> berarti dokumen yang dikeluarkan oleh QashierWise kepada Pengguna, yang mengatur paket Produk yang dipilih oleh Pengguna.",
              ]}
            />
          </Subsection>

          <Subsection label="B" title="Detail Paket, Biaya, dan Pembayaran">
            <p className="text-ink-600 leading-relaxed">
              Pengguna mengakui, memahami, dan menyetujui bahwa detail paket
              Produk yang dipilih oleh Pengguna adalah sebagaimana tercantum
              dalam Quotation dan/atau Perjanjian Pengguna.
            </p>
          </Subsection>

          <Subsection label="C" title="Representasi dan Jaminan Pengguna">
            <p className="text-ink-600 leading-relaxed">
              Pengguna menyatakan dan menjamin bahwa:
            </p>
            <BulletList
              items={[
                "Pengguna kompeten dan berwenang untuk menyetujui Syarat dan Ketentuan ini.",
                "Pengguna telah memperoleh semua lisensi yang diperlukan untuk kewajiban berdasarkan Syarat dan Ketentuan ini.",
                "Tidak ada tindakan hukum yang sedang berlangsung yang dapat mempengaruhi kemampuan Pengguna untuk melakukan kewajibannya berdasarkan Syarat dan Ketentuan ini.",
                "Pengguna mematuhi semua peraturan anti-suap dan anti-korupsi yang berlaku.",
                "Pengguna menjamin bahwa telah memperoleh persetujuan yang sah dari pemilik data pribadi yang datanya diberikan kepada QashierWise sehubungan dengan penggunaan Produk.",
                "Pengguna menjamin untuk selalu mematuhi syarat, ketentuan, dan kebijakan privasi yang berlaku untuk setiap Produk.",
              ]}
            />
          </Subsection>
        </Section>

        <Section label="II" title="Ganti Rugi dan Batasan Tanggung Jawab">
          <p className="text-ink-600 leading-relaxed mb-4">
            Pengguna setuju untuk mengganti rugi, membela, dan membebaskan
            QashierWise dari segala klaim, kerugian, kerusakan, kewajiban, dan
            biaya yang timbul dari penggunaan Produk oleh Pengguna, termasuk
            namun tidak terbatas pada pelanggaran Syarat dan Ketentuan ini.
          </p>
          <p className="text-ink-600 leading-relaxed">
            QashierWise tidak bertanggung jawab atas kerugian tidak langsung,
            insidental, khusus, atau konsekuensial yang timbul dari penggunaan
            atau ketidakmampuan menggunakan Produk.
          </p>
        </Section>

        <Section label="III" title="Keamanan Data">
          <p className="text-ink-600 leading-relaxed mb-4">
            QashierWise menerapkan langkah-langkah keamanan standar industri
            untuk melindungi informasi pribadi Anda dari akses, kehilangan,
            penyalahgunaan, atau perubahan yang tidak sah.
          </p>
          <p className="text-ink-600 leading-relaxed">
            Meskipun kami menjamin tindakan pencegahan yang wajar, tidak ada
            metode transmisi data melalui internet atau penyimpanan elektronik
            yang sepenuhnya aman. Oleh karena itu, kami tidak dapat menjamin
            keamanan mutlak.
          </p>
        </Section>

        <Section label="IV" title="Tautan Pihak Ketiga">
          <p className="text-ink-600 leading-relaxed">
            QashierWise mungkin berisi tautan ke situs web atau layanan pihak
            ketiga. Kami tidak bertanggung jawab atas praktik privasi atau
            konten situs web tersebut. Silakan tinjau kebijakan privasi mereka
            sebelum menggunakannya.
          </p>
        </Section>

        <Section label="V" title="Perubahan Ketentuan Layanan">
          <p className="text-ink-600 leading-relaxed">
            Kami dapat memperbarui Ketentuan Layanan ini dari waktu ke waktu.
            Setiap perubahan akan diposting di halaman ini, dan tanggal
            "terakhir diperbarui" akan direvisi sesuai.
          </p>
        </Section>

        <Section label="VI" title="Hubungi Kami">
          <p className="text-ink-600 leading-relaxed mb-5">
            Jika Anda memiliki pertanyaan, kekhawatiran, atau permintaan terkait
            Ketentuan Layanan ini, silakan hubungi kami di:
          </p>
          <div className="rounded-2xl bg-purple-50 border border-purple-100 p-6">
            <div className="grid gap-3 text-sm">
              <div>
                <span className="font-semibold text-ink-900">Email: </span>
                <a
                  href="mailto:admin@qashierwise.com"
                  className="text-purple-700 hover:underline"
                >
                  admin@qashierwise.com
                </a>
              </div>
            </div>
          </div>
        </Section>
      </div>
    </LegalLayout>
  );
}
