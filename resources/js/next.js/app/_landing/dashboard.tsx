"use client";

import { useState } from "react";
import {
  CalendarIcon,
  CardIcon,
  ChartIcon,
  InboxIcon,
  SparkleIcon,
  TruckIcon,
  UsersIcon,
} from "./icons";

type TabKey = "inbox" | "orders" | "reservations";

const items: Record<TabKey, { av: string; name: string; msg: string; time: string; initials: string }[]> = {
  inbox: [
    { av: "from-[#FF8A65] to-[#FF5722]", name: "Ahmad Rizki", msg: "Mau pesan ayam bakar untuk 2 orang...", time: "2m", initials: "AR" },
    { av: "from-[#4FC3F7] to-[#1976D2]", name: "Siti Nurhaliza", msg: "Apakah masih buka? Ingin reservasi...", time: "5m", initials: "SN" },
    { av: "from-[#A8C56F] to-[#689F38]", name: "Budi Santoso", msg: "Pickup jam 7 malam ya, sudah transfer...", time: "8m", initials: "BS" },
  ],
  orders: [
    { av: "from-[#4FC3F7] to-[#1976D2]", name: "Order #1284", msg: "Nasi goreng spesial × 2 · Rp 86.000", time: "1m", initials: "1" },
    { av: "from-[#A8C56F] to-[#689F38]", name: "Order #1283", msg: "Paket keluarga · Rp 215.000 · DELIVERY", time: "12m", initials: "2" },
    { av: "from-[#FF8A65] to-[#FF5722]", name: "Order #1282", msg: "Ayam geprek × 3 · Rp 75.000 · DINE-IN", time: "22m", initials: "3" },
  ],
  reservations: [
    { av: "from-[#A8C56F] to-[#689F38]", name: "Ibu Sari (4 orang)", msg: "Hari ini · 19:00 · DP Rp 50.000 paid", time: "Now", initials: "S" },
    { av: "from-[#FF8A65] to-[#FF5722]", name: "Pak Joko (2 orang)", msg: "Besok · 12:30 · belum bayar DP", time: "1h", initials: "J" },
    { av: "from-[#4FC3F7] to-[#1976D2]", name: "Mira (6 orang)", msg: "Sabtu · 18:00 · DP Rp 100.000 paid", time: "3h", initials: "M" },
  ],
};

const headTitle: Record<TabKey, string> = {
  inbox: "Recent Conversations",
  orders: "Recent Orders",
  reservations: "Upcoming Reservations",
};

export function Dashboard() {
  const [tab, setTab] = useState<TabKey>("inbox");

  const NavBtn = ({
    k,
    Icon,
    label,
    count,
  }: {
    k?: TabKey;
    Icon: typeof InboxIcon;
    label: string;
    count?: number;
  }) => {
    const active = k && tab === k;
    return (
      <button
        type="button"
        onClick={() => k && setTab(k)}
        className={`flex items-center gap-2.5 px-2.5 py-2.5 rounded-[10px] text-[13px] font-medium w-full text-left transition-colors ${
          active ? "bg-purple-50 text-purple-700 font-semibold" : "text-ink-700 hover:bg-ink-50"
        }`}
      >
        <Icon /> {label}
        {count !== undefined && (
          <span className="ml-auto bg-purple-600 text-white text-[10px] py-px px-1.5 rounded-full font-bold">{count}</span>
        )}
      </button>
    );
  };

  return (
    <section className="reveal mx-4 sm:mx-7 mb-24 rounded-[28px] sm:rounded-[36px] bg-gradient-to-b from-[#FAF8FE] to-[#F2EBFF] pt-14 sm:pt-20 px-5 sm:px-10 overflow-hidden relative">
      <div className="text-center max-w-[640px] mx-auto">
        <div className="inline-flex items-center gap-2 text-[13px] font-semibold tracking-wider text-purple-700 px-3 py-1.5 rounded-full border border-[rgba(124,58,237,0.18)] bg-white/60">
          <SparkleIcon /> Console
        </div>
        <h2 className="mt-4 font-extrabold leading-[1.05] -tracking-[0.03em] text-[clamp(32px,4vw,52px)]">
          Satu dashboard untuk semua
        </h2>
        <p className="mt-3 text-ink-500 font-medium leading-[1.5] text-[clamp(16px,1.4vw,19px)]">
          Kelola chat, pesanan, reservasi, dan laporan dalam satu tampilan yang tenang.
        </p>
      </div>

      <div className="mt-14 max-w-[920px] mx-auto bg-white rounded-t-[18px] overflow-hidden translate-y-5 shadow-[0_-2px_0_rgba(255,255,255,0.5)_inset,0_20px_50px_rgba(63,26,140,0.2),0_40px_80px_rgba(63,26,140,0.15)]">
        <div className="h-9 bg-ink-100 flex items-center px-3.5 gap-2 border-b border-ink-100">
          <span className="w-3 h-3 rounded-full bg-[#FF5F57]" />
          <span className="w-3 h-3 rounded-full bg-[#FEBC2E]" />
          <span className="w-3 h-3 rounded-full bg-[#28C840]" />
          <div className="mx-auto bg-white rounded-md py-1 px-3.5 text-[11px] text-ink-500">app.qashierwise.com</div>
        </div>
        <div className="grid sm:grid-cols-[200px_1fr] min-h-[420px]">
          <aside className="hidden sm:block bg-[#FAFAFB] border-r border-ink-100 p-4 pt-[18px]">
            <div className="flex items-center gap-2 text-sm font-extrabold pb-4 px-2">
              <div className="w-[22px] h-[22px] rounded-md grid place-items-center text-white text-xs font-extrabold bg-gradient-to-br from-purple-500 to-purple-700">
                Q
              </div>
              QashierWise
            </div>
            <div className="grid gap-0.5">
              <NavBtn k="inbox" Icon={InboxIcon} label="Inbox" count={3} />
              <NavBtn k="orders" Icon={TruckIcon} label="Orders" />
              <NavBtn k="reservations" Icon={CalendarIcon} label="Reservations" />
              <NavBtn Icon={CardIcon} label="Payments" />
              <NavBtn Icon={UsersIcon} label="Customers" />
              <NavBtn Icon={ChartIcon} label="Reports" />
            </div>
          </aside>
          <main className="p-5 px-[22px]">
            <div className="flex justify-between items-center mb-3.5">
              <h4 className="text-base font-bold">{headTitle[tab]}</h4>
              <span className="text-[11px] font-bold py-1 px-2.5 rounded-full bg-[#E8F8EC] text-[#1F9B3D]">● Online</span>
            </div>
            <div>
              {items[tab].map((c, i) => (
                <div
                  key={tab + i}
                  className="flex items-center gap-3 p-3 rounded-[14px] bg-ink-50 mb-2 transition-all hover:bg-purple-50 hover:translate-x-0.5"
                >
                  <div className={`w-9 h-9 rounded-full grid place-items-center text-white font-bold text-[13px] bg-gradient-to-br ${c.av}`}>
                    {c.initials}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="text-[13px] font-bold">{c.name}</div>
                    <div className="text-xs text-ink-500 truncate">{c.msg}</div>
                  </div>
                  <div className="text-[10px] text-ink-400">{c.time}</div>
                </div>
              ))}
            </div>
          </main>
        </div>
      </div>
    </section>
  );
}
