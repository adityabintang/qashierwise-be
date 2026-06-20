// Sidebar structure for the documentation. A `group` renders as an expandable
// dropdown; an `item` renders as a single top-level link. `slug` matches a file
// under `resources/docs` (without `.md`). Order here = order in the sidebar.

export type DocLink = { slug: string; label: string };
export type DocNode =
  | { type: "item"; slug: string; label: string; icon?: string }
  | { type: "group"; label: string; icon?: string; items: DocLink[] };

export const DOCS_BASE = "/docs";
export const DEFAULT_SLUG = "overview";

export const nav: DocNode[] = [
  { type: "item", slug: "overview", label: "Overview", icon: "home" },
  {
    type: "group",
    label: "Memulai",
    icon: "rocket",
    items: [
      { slug: "getting-started/registrasi", label: "Daftar & Masuk" },
      { slug: "getting-started/dashboard", label: "Mengenal Dashboard" },
    ],
  },
  {
    type: "group",
    label: "WhatsApp",
    icon: "message",
    items: [
      { slug: "whatsapp/hubungkan-akun", label: "Hubungkan Akun" },
      { slug: "whatsapp/inbox", label: "Inbox / Messages" },
      { slug: "whatsapp/kontak", label: "Kontak" },
      { slug: "whatsapp/template", label: "Template Pesan" },
    ],
  },
  {
    type: "group",
    label: "AI Agent",
    icon: "bot",
    items: [
      { slug: "ai-agent/overview", label: "Pengenalan" },
      { slug: "ai-agent/konfigurasi", label: "Konfigurasi" },
      { slug: "ai-agent/uji-coba", label: "Uji Coba" },
    ],
  },
  {
    type: "group",
    label: "Katalog",
    icon: "catalog",
    items: [
      { slug: "katalog/overview", label: "Pengenalan" },
      { slug: "katalog/hubungkan-katalog", label: "Hubungkan Katalog" },
      { slug: "katalog/create-product", label: "Membuat Produk" },
      { slug: "katalog/update-product", label: "Memperbarui Produk" },
    ],
  },
  {
    type: "group",
    label: "POS Kasir",
    icon: "pos",
    items: [
      { slug: "pos/overview", label: "Pengenalan" },
      { slug: "pos/toko", label: "Toko" },
      { slug: "pos/kategori", label: "Kategori" },
      { slug: "pos/produk", label: "Produk" },
      { slug: "pos/meja", label: "Meja" },
      { slug: "pos/pesanan", label: "Pesanan" },
      { slug: "pos/pembayaran", label: "Pembayaran" },
      { slug: "pos/transaksi", label: "Transaksi" },
      { slug: "pos/laporan", label: "Laporan" },
      { slug: "pos/pengguna-peran", label: "Pengguna & Peran" },
    ],
  },
  {
    type: "group",
    label: "Reservasi",
    icon: "calendar",
    items: [
      { slug: "reservasi/overview", label: "Pengenalan" },
      { slug: "reservasi/konfigurasi", label: "Konfigurasi" },
      { slug: "reservasi/kalender", label: "Kalender & Daftar" },
    ],
  },
  {
    type: "group",
    label: "Delivery",
    icon: "truck",
    items: [
      { slug: "delivery/overview", label: "Pengenalan" },
      { slug: "delivery/konfigurasi", label: "Konfigurasi" },
      { slug: "delivery/komplain", label: "Komplain" },
    ],
  },
  {
    type: "group",
    label: "CRM",
    icon: "users",
    items: [{ slug: "crm/customer-tags", label: "Customer Tags" }],
  },
  {
    type: "group",
    label: "Merchant",
    icon: "store",
    items: [
      { slug: "merchant/registrasi-sub-merchant", label: "Registrasi Sub-Merchant" },
    ],
  },
  {
    type: "group",
    label: "Pembayaran",
    icon: "wallet",
    items: [
      { slug: "pembayaran/sub-merchant", label: "Sub-Merchant" },
      { slug: "pembayaran/qris", label: "QRIS" },
      { slug: "pembayaran/saldo-penarikan", label: "Saldo & Penarikan" },
    ],
  },
  {
    type: "group",
    label: "Langganan",
    icon: "star",
    items: [
      { slug: "langganan/paket-harga", label: "Paket & Harga" },
      { slug: "langganan/kelola", label: "Mengelola Langganan" },
    ],
  },
  {
    type: "group",
    label: "Developer",
    icon: "code",
    items: [{ slug: "developer/webhooks", label: "Webhooks" }],
  },
];

/** Flat, ordered list of slugs — used for prev/next navigation. */
export const flatSlugs: DocLink[] = nav.flatMap((node) =>
  node.type === "item" ? [{ slug: node.slug, label: node.label }] : node.items,
);

/** Which group (label) contains a slug, so the sidebar can auto-expand it. */
export function groupOf(slug: string): string | null {
  for (const node of nav) {
    if (node.type === "group" && node.items.some((i) => i.slug === slug)) {
      return node.label;
    }
  }
  return null;
}
