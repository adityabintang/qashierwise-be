import { useEffect, useState } from "react";
import { Chart } from "../components/Chart";
import { PageHeader } from "../components/Layout";
import { analyticsApi, type AnalyticsData } from "../api";
import { Card, PageLoader } from "../ui";
import { useToast } from "../toast";

const definitions = [
  ["registered", "Merchant terdaftar", "Akun utama merchant, tanpa staf POS, penulis CMS, dan Super Admin."],
  ["active", "Merchant aktif", "Merchant dengan pesan WhatsApp, order, atau reservasi dalam 30 hari terakhir."],
  ["paid", "Paid", "Periode langganan berlaku dengan pembayaran berhasil pada periode tersebut."],
  ["unpaid", "Unpaid", "Tanpa periode langganan berlaku, termasuk trial dan pembayaran tertunda."],
  ["unverified", "Belum terverifikasi", "Periode langganan berlaku, tetapi bukti bayar periode ini belum tercatat."],
  ["messages_total", "Pesan tercatat", "Seluruh pesan WhatsApp masuk dan keluar yang masih tersimpan, termasuk yang gagal."],
  ["messages_incoming", "Pesan masuk", "Pesan WhatsApp masuk yang masih tersimpan."],
  ["messages_outgoing", "Pesan keluar", "Pesan WhatsApp keluar yang masih tersimpan."],
  ["whatsapp_active", "Merchant WhatsApp aktif", "Merchant dengan minimal satu akun WhatsApp berstatus aktif."],
] as const;

function exportCsv(data: AnalyticsData) {
  const rows: (string | number)[][] = [
    ["Laporan Internal QashierWise"],
    ["Diambil pada (WIB)", data.as_of],
    ["Aktivitas sejak (WIB)", data.activity_from],
    ["Metrik", "Jumlah", "Definisi"],
    ...definitions.map(([key, label, definition]) => [label, data.stats[key], definition]),
    [],
    ["Bulan (WIB)", "Merchant baru", "Bulan berjalan"],
    ...data.months.map((month) => [month.month, month.count, month.partial ? "Ya" : "Tidak"]),
  ];
  const csv = "\uFEFF" + rows.map((row) =>
    row.map((value) => `"${String(value).replaceAll('"', '""')}"`).join(",")
  ).join("\r\n");
  const url = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
  const link = document.createElement("a");
  link.href = url;
  link.download = `qashierwise-analytics-${data.as_of.slice(0, 10)}.csv`;
  link.click();
  URL.revokeObjectURL(url);
}

export function Analytics() {
  const [data, setData] = useState<AnalyticsData | null>(null);
  const [failed, setFailed] = useState(false);
  const toast = useToast();

  useEffect(() => {
    analyticsApi.get().then(setData).catch(() => {
      setFailed(true);
      toast.error("Gagal memuat laporan internal");
    });
  }, [toast]);

  if (failed) return <p className="py-16 text-center text-ink-500">Laporan belum dapat dimuat.</p>;
  if (!data) return <PageLoader />;

  const number = new Intl.NumberFormat("id-ID");
  const timestamp = new Intl.DateTimeFormat("id-ID", {
    timeZone: "Asia/Jakarta", dateStyle: "long", timeStyle: "short",
  });

  return (
    <div className="admin-fade-in">
      <PageHeader title="Laporan Internal" description="Pertumbuhan merchant dan penggunaan QashierWise" />
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-ink-500">Diambil {timestamp.format(new Date(data.as_of))} WIB</p>
        <button onClick={() => exportCsv(data)} className="rounded-xl bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700">
          Unduh CSV
        </button>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {definitions.map(([key, label, definition]) => (
          <Card key={key} className="p-5">
            <p className="text-sm font-medium text-ink-500">{label}</p>
            <p className="mt-2 text-3xl font-bold text-ink-900">{number.format(data.stats[key])}</p>
            <p className="mt-2 text-xs leading-relaxed text-ink-400">{definition}</p>
          </Card>
        ))}
      </div>

      <Card className="mt-6 p-5">
        <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
          <h2 className="text-base font-semibold text-ink-900">Pertumbuhan merchant dalam 12 bulan</h2>
          <p className="text-xs text-ink-400">Pendaftaran baru per bulan · bulan terakhir berjalan</p>
        </div>
        <Chart options={{
          chart: { type: "area", height: 320, toolbar: { show: false }, fontFamily: "inherit" },
          series: [{ name: "Merchant baru", data: data.months.map((month) => month.count) }],
          xaxis: { categories: data.months.map((month) => month.month), labels: { rotate: -45 } },
          colors: ["#7c3aed"],
          stroke: { curve: "smooth", width: 3 },
          fill: { type: "gradient", gradient: { opacityFrom: 0.35, opacityTo: 0.03 } },
          dataLabels: { enabled: false },
          yaxis: { min: 0, labels: { formatter: (value) => number.format(Math.round(value)) } },
          grid: { borderColor: "#eee" },
        }} />
      </Card>
      <p className="mt-4 text-xs text-ink-400">
        Aktivitas dihitung sejak {timestamp.format(new Date(data.activity_from))} WIB. Angka laporan berasal dari data yang masih tersimpan.
      </p>
    </div>
  );
}
