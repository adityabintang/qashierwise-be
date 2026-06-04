// Lightweight i18n for the public React pages. The active locale is provided by
// Laravel (LocalizationMiddleware → app()->getLocale()) and injected by the blade
// host as `window.__LOCALE__`. Switching language goes through Laravel's
// `/language/{locale}` route so it stays consistent with the rest of the app.

export type Locale = "id" | "en";

function detectLocale(): Locale {
  const l = typeof window !== "undefined" ? (window as unknown as { __LOCALE__?: string }).__LOCALE__ : "id";
  return l === "en" ? "en" : "id";
}

export const locale: Locale = detectLocale();
export const otherLocale: Locale = locale === "en" ? "id" : "en";

const id = {
  nav: { how: "Cara Kerja", features: "Fitur", pricing: "Harga", docs: "Docs", blog: "Blog", about: "Tentang", faq: "FAQ", cta: "Coba Gratis 14 Hari" },
  hero: {
    badge: "Didukung AI · Terintegrasi QRIS",
    titleA: "Respon lebih cepat,",
    titleB: "jual lebih ",
    titleHighlight: "banyak",
    titleEnd: ".",
    subtitle:
      "QashierWise menghadirkan Chatbot WhatsApp berbasis AI untuk restoran — dengan satu layar admin: Inbox, Pesanan, Reservasi, Menu, dan CRM.",
    cta: "Coba Gratis 14 Hari",
    bullets: [
      "Reservasi & Order via WhatsApp tanpa ribet",
      "Mulai pesanan + laporan dengan mudah",
      "Tanpa pelatihan workflow (bayar di tempat)",
      "Laporan Order & penjualan otomatis",
    ],
    sales: "Penjualan",
    inbox: "Pesan Masuk",
    inboxValue: "4 baru",
  },
  how: {
    eyebrow: "Cara Kerja",
    title: "Otomatis dengan AI + QRIS",
    subtitle: "Semuanya untuk bisnis restoran: cepat, ringkas, dan akurat.",
    steps: [
      { title: "Terima chat di WhatsApp", desc: "Pelanggan kirim chat ke nomor restoran Anda — tanpa app baru, tanpa training." },
      { title: "AI merespons otomatis", desc: "AI menjawab menu, jam buka, reservasi, dan pertanyaan umum dalam hitungan detik." },
      { title: "Bayar dengan QRIS", desc: "Kirim invoice QRIS otomatis — Midtrans, Xendit, atau OkeOce. Status terdeteksi instan." },
      { title: "Pantau di satu Console", desc: "Lihat semua pesanan, reservasi & laporan dalam satu dashboard yang tenang." },
    ],
  },
  features: {
    eyebrow: "Fitur",
    title: "Fitur utama yang restoran butuhkan",
    subtitle: "Semua alat & aplikasi dibuat untuk memudahkan pemasukan restoran.",
    items: [
      { title: "AI Chatbot WhatsApp", desc: "Chatbot dengan model AI, terima order, jawab ke staff, handover ke live agent kapan saja." },
      { title: "Console Satu Layar", desc: "Inbox pesan, pesanan, status reservasi, dan semuanya dalam satu dashboard." },
      { title: "Order Delivery & Pickup", desc: "Penjadwalan, order & delivery rute terdekat, pembayaran QRIS terintegrasi." },
      { title: "Reservasi Pintar", desc: "Slot ketersediaan, jam buka, kapasitas, dan notifikasi staff. Penjagaan T-24h & T-1hr." },
      { title: "Pembayaran QRIS", desc: "Kirim invoice QRIS atau terima QR statis via chat, semua tercatat otomatis." },
      { title: "Laporan & CRM", desc: "CRM, repeat rate, tag pelanggan, dan export CSV (Pro)." },
    ],
  },
  integrations: {
    eyebrow: "Integrasi",
    title: "Integrasi yang didukung",
    subtitle: "Sambungkan QashierWise untuk bisnis Anda — tanpa ribet.",
  },
  dashboard: {
    eyebrow: "Console",
    title: "Satu dashboard untuk semua",
    subtitle: "Kelola chat, pesanan, reservasi, dan laporan dalam satu tampilan yang tenang.",
  },
  pricing: {
    eyebrow: "Harga",
    title: "Harga sederhana, tumbuh bersama Anda",
    subtitle: "Mulai gratis — upgrade kapan saja.",
    options: ["1 Bulan", "3 Bulan", "1 Tahun"],
    popular: "Populer",
    basicName: "Basic",
    basicTag: "1 outlet · No admin · 4 pickup",
    basicPriceSuffix: "/selamanya",
    basicCta: "Mulai Gratis",
    proName: "Pro",
    proTag: "1 outlet · delivery + QRIS",
    proPriceSuffix: "/bln",
    proCta: "Pilih Pro",
    basicFeatures: [
      "AI chatbot dasar (% pesan/bulan)",
      "Reservasi & pickup orders",
      "Webhook menu digital",
      "Tanpa pembayaran online (bayar di tempat)",
      "1 user staff · Email support (24h)",
    ],
    proFeatures: [
      "Delivery + airtime & bayar",
      "Pembayaran QRIS unlimited",
      "Reservasi & order auto-confirm",
      "All pesan/bulan + Chat support (1hr)",
      "Customer Base",
      "Analytics + export CSV",
      "Webhook & API",
    ],
  },
  about: {
    eyebrow: "Tentang",
    title: "Kenali lebih dekat QashierWise",
    subtitle: "Misi kami: bikin restoran Indonesia bisa fokus pada pelanggan — bukan operasional.",
    founder: "Founder",
    name: "Aditya Bintang Fadila",
    bio: "QashierWise dibangun atas keyakinan bahwa restoran Indonesia berhak punya teknologi yang terasa ringan, modern, dan terjangkau — sama seperti aplikasi yang Anda pakai sehari-hari.",
  },
  faq: {
    eyebrow: "FAQ",
    title: "Pertanyaan yang sering diajukan",
    subtitle: "Semua dalam Bahasa Indonesia.",
    items: [
      { q: "Apa itu QashierWise?", a: "QashierWise adalah platform Chatbot WhatsApp berbasis AI + sistem reservasi & pembayaran QRIS, dirancang khusus untuk restoran di Indonesia." },
      { q: "Apakah perlu aplikasi terpisah untuk pelanggan?", a: "Tidak. Pelanggan cukup chat via WhatsApp — tanpa download app baru. Staff Anda menggunakan satu Console berbasis web." },
      { q: "Bagaimana pembayaran dilakukan?", a: "Pembayaran via QRIS yang terintegrasi dengan Midtrans, Xendit, atau OkeOce. Anda juga bisa terima 'bayar di tempat' tanpa biaya." },
      { q: "Apakah bisa multi-outlet atau multi-nomor?", a: "Bisa pada paket Pro+. Satu Console bisa menangani beberapa outlet, masing-masing dengan nomor WhatsApp dan menu yang terpisah." },
      { q: "Apakah ada masa percobaan?", a: "Ya. Anda bisa mencoba semua fitur Pro selama 14 hari — tanpa kartu kredit. Setelah trial, downgrade ke Basic atau lanjut ke Pro." },
    ],
  },
  cta: {
    badge: "Siap mencoba?",
    titleA: "Mulai dengan gratis hari ini.",
    titleB: "Upgrade saat Anda siap.",
    subtitle: "Tanpa kartu kredit · Setup < 10 menit · Bahasa Indonesia",
    cta: "Coba Gratis 14 Hari",
  },
  footer: {
    tagline: "Coba gratis 14 hari. QashierWise membantu restoran menerima reservasi & order via WhatsApp dengan mudah.",
    addressLabel: "Alamat",
    colNav: "Navigasi",
    colLegal: "Legal",
    colProduct: "Produk",
    navLinks: ["Fitur", "Harga", "Tentang Kami", "FAQ"],
    legalLinks: ["Kebijakan Privasi", "Ketentuan Layanan", "Kebijakan Pengembalian"],
    productLinks: ["QashierWise Console", "Chatbot WhatsApp", "QRIS Integration"],
    rights: "© 2026 QashierWise by Aditya Bintang Fadila. All Rights Reserved.",
  },
  demo: { watch: "Lihat Demo", close: "Tutup video" },
};

const en: typeof id = {
  nav: { how: "How It Works", features: "Features", pricing: "Pricing", docs: "Docs", blog: "Blog", about: "About", faq: "FAQ", cta: "Try Free for 14 Days" },
  hero: {
    badge: "AI-Powered · QRIS Integrated",
    titleA: "Respond faster,",
    titleB: "sell ",
    titleHighlight: "more",
    titleEnd: ".",
    subtitle:
      "QashierWise brings an AI-powered WhatsApp chatbot to restaurants — with one admin screen: Inbox, Orders, Reservations, Menu, and CRM.",
    cta: "Try Free for 14 Days",
    bullets: [
      "Reservations & orders via WhatsApp, hassle-free",
      "Start orders + reports with ease",
      "No workflow training (pay on the spot)",
      "Automatic order & sales reports",
    ],
    sales: "Sales",
    inbox: "New Messages",
    inboxValue: "4 new",
  },
  how: {
    eyebrow: "How It Works",
    title: "Automated with AI + QRIS",
    subtitle: "Everything for your restaurant business: fast, concise, and accurate.",
    steps: [
      { title: "Receive chats on WhatsApp", desc: "Customers message your restaurant's number — no new app, no training." },
      { title: "AI responds automatically", desc: "AI answers menu, opening hours, reservations, and common questions in seconds." },
      { title: "Pay with QRIS", desc: "Send QRIS invoices automatically — Midtrans, Xendit, or OkeOce. Status detected instantly." },
      { title: "Monitor in one Console", desc: "See all orders, reservations & reports in one calm dashboard." },
    ],
  },
  features: {
    eyebrow: "Features",
    title: "The core features restaurants need",
    subtitle: "Every tool & app built to grow your restaurant's revenue.",
    items: [
      { title: "AI WhatsApp Chatbot", desc: "An AI-model chatbot that takes orders, replies to staff, and hands over to a live agent anytime." },
      { title: "One-Screen Console", desc: "Message inbox, orders, reservation status — everything in one dashboard." },
      { title: "Delivery & Pickup Orders", desc: "Scheduling, orders & nearest-route delivery, integrated QRIS payments." },
      { title: "Smart Reservations", desc: "Availability slots, opening hours, capacity, and staff notifications. T-24h & T-1hr reminders." },
      { title: "QRIS Payments", desc: "Send QRIS invoices or accept static QR via chat — all recorded automatically." },
      { title: "Reports & CRM", desc: "CRM, repeat rate, customer tags, and CSV export (Pro)." },
    ],
  },
  integrations: {
    eyebrow: "Integrations",
    title: "Supported integrations",
    subtitle: "Connect QashierWise to your business — hassle-free.",
  },
  dashboard: {
    eyebrow: "Console",
    title: "One dashboard for everything",
    subtitle: "Manage chats, orders, reservations, and reports in one calm view.",
  },
  pricing: {
    eyebrow: "Pricing",
    title: "Simple pricing that grows with you",
    subtitle: "Start free — upgrade anytime.",
    options: ["1 Month", "3 Months", "1 Year"],
    popular: "Popular",
    basicName: "Basic",
    basicTag: "1 outlet · No admin · 4 pickup",
    basicPriceSuffix: "/forever",
    basicCta: "Start Free",
    proName: "Pro",
    proTag: "1 outlet · delivery + QRIS",
    proPriceSuffix: "/mo",
    proCta: "Choose Pro",
    basicFeatures: [
      "Basic AI chatbot (% messages/month)",
      "Reservations & pickup orders",
      "Digital menu webhook",
      "No online payment (pay on the spot)",
      "1 staff user · Email support (24h)",
    ],
    proFeatures: [
      "Delivery + payments",
      "Unlimited QRIS payments",
      "Auto-confirm reservations & orders",
      "All messages/month + Chat support (1hr)",
      "Customer Base",
      "Analytics + CSV export",
      "Webhook & API",
    ],
  },
  about: {
    eyebrow: "About",
    title: "Get to know QashierWise",
    subtitle: "Our mission: help Indonesian restaurants focus on customers — not operations.",
    founder: "Founder",
    name: "Aditya Bintang Fadila",
    bio: "QashierWise was built on the belief that Indonesian restaurants deserve technology that feels light, modern, and affordable — just like the apps you use every day.",
  },
  faq: {
    eyebrow: "FAQ",
    title: "Frequently asked questions",
    subtitle: "Answers to common questions.",
    items: [
      { q: "What is QashierWise?", a: "QashierWise is an AI-powered WhatsApp chatbot platform + reservation & QRIS payment system, built specifically for restaurants in Indonesia." },
      { q: "Do customers need a separate app?", a: "No. Customers simply chat via WhatsApp — no new download. Your staff use a single web-based Console." },
      { q: "How are payments made?", a: "Payments via QRIS integrated with Midtrans, Xendit, or OkeOce. You can also accept 'pay on the spot' at no cost." },
      { q: "Can it handle multiple outlets or numbers?", a: "Yes, on Pro+ plans. One Console can manage several outlets, each with its own WhatsApp number and menu." },
      { q: "Is there a trial period?", a: "Yes. You can try all Pro features for 14 days — no credit card. After the trial, downgrade to Basic or continue with Pro." },
    ],
  },
  cta: {
    badge: "Ready to try?",
    titleA: "Start free today.",
    titleB: "Upgrade when you're ready.",
    subtitle: "No credit card · Setup < 10 min · Bahasa Indonesia & English",
    cta: "Try Free for 14 Days",
  },
  footer: {
    tagline: "14-day free trial. QashierWise helps restaurants accept reservations & orders via WhatsApp with ease.",
    addressLabel: "Address",
    colNav: "Navigation",
    colLegal: "Legal",
    colProduct: "Product",
    navLinks: ["Features", "Pricing", "About Us", "FAQ"],
    legalLinks: ["Privacy Policy", "Terms of Service", "Refund Policy"],
    productLinks: ["QashierWise Console", "WhatsApp Chatbot", "QRIS Integration"],
    rights: "© 2026 QashierWise by Aditya Bintang Fadila. All Rights Reserved.",
  },
  demo: { watch: "Watch Demo", close: "Close video" },
};

const dict = { id, en };

export const t = dict[locale];
