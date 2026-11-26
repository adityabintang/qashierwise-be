import { useEffect, useState } from "react";
import { Chart } from "../components/Chart";
import { PageHeader } from "../components/Layout";
import { analyticsApi, type AnalyticsData } from "../api";
import { Card, PageLoader } from "../ui";
import { useToast } from "../toast";
import { adminLocale, tr } from "../locale";

const definitions = [
  ["registered", tr("Merchant terdaftar", "Registered merchants"), tr("Akun utama merchant, tanpa staf POS, penulis CMS, dan Super Admin.", "Primary merchant accounts, excluding POS staff, CMS authors, and super admins.")],
  ["active", tr("Merchant aktif", "Active merchants"), tr("Merchant dengan pesan WhatsApp, order, atau reservasi dalam 30 hari terakhir.", "Merchants with WhatsApp messages, orders, or reservations in the last 30 days.")],
  ["paid", tr("Berbayar", "Paid"), tr("Periode langganan berlaku dengan pembayaran berhasil pada periode tersebut.", "Active subscription period with a successful payment in that period.")],
  ["unpaid", tr("Belum berbayar", "Unpaid"), tr("Tanpa periode langganan berlaku, termasuk trial dan pembayaran tertunda.", "No active subscription period, including trials and pending payments.")],
  ["unverified", tr("Belum terverifikasi", "Unverified"), tr("Periode langganan berlaku, tetapi bukti bayar periode ini belum tercatat.", "Active subscription period without a recorded payment for that period.")],
  ["messages_total", tr("Pesan tercatat", "Recorded messages"), tr("Seluruh pesan WhatsApp masuk dan keluar yang masih tersimpan, termasuk yang gagal.", "All stored incoming and outgoing WhatsApp messages, including failed messages.")],
  ["messages_incoming", tr("Pesan masuk", "Incoming messages"), tr("Pesan WhatsApp masuk yang masih tersimpan.", "Stored incoming WhatsApp messages.")],
  ["messages_outgoing", tr("Pesan keluar", "Outgoing messages"), tr("Pesan WhatsApp keluar yang masih tersimpan.", "Stored outgoing WhatsApp messages.")],
  ["whatsapp_active", tr("Merchant WhatsApp aktif", "Active WhatsApp merchants"), tr("Merchant dengan minimal satu akun WhatsApp berstatus aktif.", "Merchants with at least one active WhatsApp account.")],
] as const;

function exportCsv(data: AnalyticsData) {
  const rows: (string | number)[][] = [
    [tr("Laporan Internal QashierWise", "QashierWise Internal Report")],
    [tr("Diambil pada (WIB)", "Generated at (WIB)"), data.as_of],
    [tr("Aktivitas sejak (WIB)", "Activity since (WIB)"), data.activity_from],
    [tr("Metrik", "Metric"), tr("Jumlah", "Count"), tr("Definisi", "Definition")],
    ...definitions.map(([key, label, definition]) => [label, data.stats[key], definition]),
    [],
    [tr("Bulan (WIB)", "Month (WIB)"), tr("Merchant baru", "New merchants"), tr("Bulan berjalan", "Current month")],
    ...data.months.map((month) => [month.month, month.count, month.partial ? tr("Ya", "Yes") : tr("Tidak", "No")]),
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
      toast.error(tr("Gagal memuat laporan internal", "Failed to load internal report"));
    });
  }, [toast]);

  if (failed) return <p className="py-16 text-center text-ink-500">{tr("Laporan belum dapat dimuat.", "The report could not be loaded.")}</p>;
  if (!data) return <PageLoader />;

  const number = new Intl.NumberFormat(adminLocale === "en" ? "en-US" : "id-ID");
  const timestamp = new Intl.DateTimeFormat(adminLocale === "en" ? "en-US" : "id-ID", {
    timeZone: "Asia/Jakarta", dateStyle: "long", timeStyle: "short",
  });

  return (
    <div className="admin-fade-in">
      <PageHeader title={tr("Laporan Internal", "Internal Report")} description={tr("Pertumbuhan merchant dan penggunaan QashierWise", "Merchant growth and QashierWise usage")} />
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-ink-500">{tr("Diambil", "Generated")} {timestamp.format(new Date(data.as_of))} WIB</p>
        <button onClick={() => exportCsv(data)} className="rounded-xl bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700">
          {tr("Unduh CSV", "Download CSV")}
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
          <h2 className="text-base font-semibold text-ink-900">{tr("Pertumbuhan merchant dalam 12 bulan", "Merchant growth over 12 months")}</h2>
          <p className="text-xs text-ink-400">{tr("Pendaftaran baru per bulan · bulan terakhir berjalan", "New registrations per month · current month is partial")}</p>
        </div>
        <Chart options={{
          chart: { type: "area", height: 320, toolbar: { show: false }, fontFamily: "inherit" },
          series: [{ name: tr("Merchant baru", "New merchants"), data: data.months.map((month) => month.count) }],
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
        {tr("Aktivitas dihitung sejak", "Activity counted since")} {timestamp.format(new Date(data.activity_from))} WIB. {tr("Angka laporan berasal dari data yang masih tersimpan.", "Report figures come from retained data.")}
      </p>
    </div>
  );
}
