export type AdminLocale = "id" | "en";

export const adminLocale: AdminLocale =
  typeof window !== "undefined" &&
  (window as Window & { __LOCALE__?: string }).__LOCALE__ === "en"
    ? "en"
    : "id";

export const tr = (id: string, en: string): string =>
  adminLocale === "en" ? en : id;

export const postStatusLabel = (status: string): string => {
  switch (status) {
    case "published": return tr("Dipublikasi", "Published");
    case "scheduled": return tr("Terjadwal", "Scheduled");
    default: return "Draft";
  }
};

export function LanguagePicker({ light = false }: { light?: boolean }) {
  return (
    <div className={`inline-flex items-center gap-1 rounded-lg p-1 text-xs font-semibold ${light ? "bg-white/15 text-white" : "bg-ink-100 text-ink-500"}`} aria-label={tr("Bahasa", "Language")}>
      <a href="/language/id" lang="id" aria-current={adminLocale === "id" ? "true" : undefined} className={`rounded-md px-2 py-1 ${adminLocale === "id" ? (light ? "bg-white text-purple-700" : "bg-white text-purple-700 shadow-sm") : ""}`}>ID</a>
      <a href="/language/en" lang="en" aria-current={adminLocale === "en" ? "true" : undefined} className={`rounded-md px-2 py-1 ${adminLocale === "en" ? (light ? "bg-white text-purple-700" : "bg-white text-purple-700 shadow-sm") : ""}`}>EN</a>
    </div>
  );
}
