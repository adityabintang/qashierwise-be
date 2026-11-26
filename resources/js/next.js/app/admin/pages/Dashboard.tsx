import {
  FileText,
  CheckCircle2,
  PencilLine,
  CalendarClock,
  FolderTree,
  Tags,
} from "lucide-react";
import { useEffect, useState } from "react";
import { dashboardApi, errorMessage, type DashboardData } from "../api";
import { PageHeader } from "../components/Layout";
import { Chart } from "../components/Chart";
import { Badge, Card, PageLoader } from "../ui";
import { useToast } from "../toast";
import { Link } from "../router";
import { formatDate } from "../format";
import { postStatusLabel, tr } from "../locale";

const PURPLE = "#7c3aed";

export function Dashboard() {
  const [data, setData] = useState<DashboardData | null>(null);
  const toast = useToast();

  useEffect(() => {
    dashboardApi
      .stats()
      .then(setData)
      .catch((err) => toast.error(errorMessage(err, tr("Gagal memuat dasbor", "Failed to load dashboard"))));
  }, [toast]);

  if (!data) return <PageLoader />;

  const stats = [
    { label: tr("Total Artikel", "Total Posts"), value: data.stats.total_posts, icon: FileText, color: "text-purple-600 bg-purple-100" },
    { label: tr("Dipublikasi", "Published"), value: data.stats.published_posts, icon: CheckCircle2, color: "text-emerald-600 bg-emerald-100" },
    { label: "Draft", value: data.stats.draft_posts, icon: PencilLine, color: "text-amber-600 bg-amber-100" },
    { label: tr("Terjadwal", "Scheduled"), value: data.stats.scheduled_posts, icon: CalendarClock, color: "text-sky-600 bg-sky-100" },
    { label: tr("Kategori", "Categories"), value: data.stats.total_categories, icon: FolderTree, color: "text-fuchsia-600 bg-fuchsia-100" },
    { label: tr("Tag", "Tags"), value: data.stats.total_tags, icon: Tags, color: "text-ink-500 bg-ink-100" },
  ];

  return (
    <div className="admin-fade-in">
      <PageHeader title={tr("Dasbor", "Dashboard")} description={tr("Ringkasan konten blog QashierWise", "QashierWise blog content overview")} />

      <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
        {stats.map((s) => (
          <Card key={s.label} className="p-4">
            <div className={`mb-3 grid h-9 w-9 place-items-center rounded-xl ${s.color}`}>
              <s.icon className="h-[18px] w-[18px]" />
            </div>
            <p className="text-2xl font-bold text-ink-900">{s.value}</p>
            <p className="text-xs text-ink-400">{s.label}</p>
          </Card>
        ))}
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-3">
        <Card className="p-5 lg:col-span-2">
          <h2 className="mb-4 text-sm font-semibold text-ink-700">{tr("Artikel per Bulan", "Posts per Month")}</h2>
          <Chart
            options={{
              chart: { type: "area", height: 280, toolbar: { show: false }, fontFamily: "inherit" },
              series: [
                {
                  name: tr("Artikel", "Posts"),
                  data: data.posts_per_month.map((m) => m.count),
                },
              ],
              xaxis: { categories: data.posts_per_month.map((m) => m.label) },
              colors: [PURPLE],
              stroke: { curve: "smooth", width: 2 },
              fill: {
                type: "gradient",
                gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 },
              },
              dataLabels: { enabled: false },
              grid: { borderColor: "#eee" },
            }}
          />
        </Card>

        <Card className="p-5">
          <h2 className="mb-4 text-sm font-semibold text-ink-700">{tr("Kategori Populer", "Popular Categories")}</h2>
          {data.popular_categories.length === 0 ? (
            <p className="py-12 text-center text-sm text-ink-400">{tr("Belum ada data", "No data yet")}</p>
          ) : (
            <Chart
              options={{
                chart: { type: "donut", height: 280, fontFamily: "inherit" },
                series: data.popular_categories.map((c) => c.count),
                labels: data.popular_categories.map((c) => c.name),
                colors: ["#7c3aed", "#a484ff", "#c2aeff", "#ddd2ff", "#ede7ff"],
                legend: { position: "bottom" },
                dataLabels: { enabled: true },
              }}
            />
          )}
        </Card>
      </div>

      <Card className="mt-6 p-5">
        <h2 className="mb-4 text-sm font-semibold text-ink-700">{tr("Artikel Terbaru", "Recent Posts")}</h2>
        {data.recent_posts.length === 0 ? (
          <p className="py-8 text-center text-sm text-ink-400">{tr("Belum ada artikel", "No posts yet")}</p>
        ) : (
          <div className="divide-y divide-ink-100">
            {data.recent_posts.map((p) => (
              <Link
                key={p.id}
                to={`/admin/posts/${p.id}`}
                className="flex items-center justify-between gap-3 py-3 transition hover:opacity-80"
              >
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-ink-900">{p.title}</p>
                  <p className="text-xs text-ink-400">{p.category ?? tr("Tanpa kategori", "Uncategorized")}</p>
                </div>
                <div className="flex shrink-0 items-center gap-3">
                  <Badge color={p.status_color}>{postStatusLabel(p.status)}</Badge>
                  <span className="hidden text-xs text-ink-400 sm:block">
                    {formatDate(p.published_at ?? p.created_at)}
                  </span>
                </div>
              </Link>
            ))}
          </div>
        )}
      </Card>
    </div>
  );
}
