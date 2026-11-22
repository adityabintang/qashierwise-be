import {
  LayoutDashboard,
  FileText,
  FolderTree,
  Tags,
  LogOut,
  Home,
  Menu,
  X,
} from "lucide-react";
import { useState } from "react";
import { useAuth } from "../auth";
import { Link, useLocation } from "../router";
import { cn } from "../../../lib/utils";

const nav = [
  { to: "/admin", label: "Dashboard", icon: LayoutDashboard, exact: true },
  { to: "/admin/posts", label: "Artikel", icon: FileText },
  { to: "/admin/categories", label: "Kategori", icon: FolderTree },
  { to: "/admin/tags", label: "Tag", icon: Tags },
];

function isActive(path: string, to: string, exact?: boolean): boolean {
  if (exact) return path === to;
  return path === to || path.startsWith(to + "/");
}

export function Layout({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth();
  const path = useLocation();
  const [open, setOpen] = useState(false);

  const SidebarBody = (
    <div className="flex h-full flex-col">
      <div className="flex items-center gap-2.5 px-5 py-5">
        <img
          src="/images/logo-48.png"
          alt="QashierWise"
          className="h-8 w-8 rounded-xl"
        />
        <div className="leading-tight">
          <p className="text-sm font-bold text-ink-900">QashierWise</p>
          <p className="text-xs text-ink-400">CMS</p>
        </div>
      </div>

      <nav className="flex-1 space-y-1 px-3 py-2">
        {nav.map((item) => {
          const active = isActive(path, item.to, item.exact);
          const Icon = item.icon;
          return (
            <Link
              key={item.to}
              to={item.to}
              onClick={() => setOpen(false)}
              className={cn(
                "flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition",
                active
                  ? "bg-gradient-to-r from-purple-100 to-purple-50 text-purple-700"
                  : "text-ink-500 hover:bg-ink-100 hover:text-ink-900"
              )}
            >
              <Icon className={cn("h-[18px] w-[18px]", active && "text-purple-600")} />
              {item.label}
            </Link>
          );
        })}
      </nav>

      <div className="space-y-1 border-t border-ink-100 px-3 py-3">
        <a
          href="/"
          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-ink-500 transition hover:bg-ink-100 hover:text-ink-900"
        >
          <Home className="h-[18px] w-[18px]" />
          Ke Beranda
        </a>
        <button
          onClick={() => logout()}
          className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-ink-500 transition hover:bg-rose-50 hover:text-ios-red"
        >
          <LogOut className="h-[18px] w-[18px]" />
          Keluar
        </button>
      </div>
    </div>
  );

  return (
    <div className="min-h-screen">
      {/* Desktop sidebar */}
      <aside className="fixed inset-y-0 left-0 hidden w-64 border-r border-ink-100 bg-white/95 backdrop-blur lg:block">
        {SidebarBody}
      </aside>

      {/* Mobile drawer */}
      {open && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div
            className="absolute inset-0 bg-ink-900/30 backdrop-blur-sm"
            onClick={() => setOpen(false)}
          />
          <aside className="admin-fade-in absolute inset-y-0 left-0 w-64 border-r border-ink-100 bg-white">
            {SidebarBody}
          </aside>
        </div>
      )}

      <div className="lg:pl-64">
        {/* Topbar */}
        <header className="sticky top-0 z-40 flex items-center justify-between border-b border-ink-100 bg-white/80 px-4 py-3 backdrop-blur lg:px-8">
          <button
            onClick={() => setOpen((o) => !o)}
            className="rounded-lg p-2 text-ink-500 transition hover:bg-ink-100 lg:hidden"
          >
            {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </button>
          <div className="hidden lg:block" />
          <div className="flex items-center gap-3">
            <div className="text-right leading-tight">
              <p className="text-sm font-semibold text-ink-900">{user?.name}</p>
              <p className="text-xs text-ink-400">{user?.email}</p>
            </div>
            <div className="grid h-9 w-9 place-items-center rounded-full bg-purple-100 text-sm font-bold text-purple-700">
              {user?.name?.charAt(0).toUpperCase() ?? "?"}
            </div>
          </div>
        </header>

        <main className="mx-auto max-w-6xl px-4 py-6 lg:px-8 lg:py-8">{children}</main>
      </div>
    </div>
  );
}

export function PageHeader({
  title,
  description,
  action,
}: {
  title: string;
  description?: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-ink-900">{title}</h1>
        {description && <p className="mt-1 text-sm text-ink-400">{description}</p>}
      </div>
      {action}
    </div>
  );
}
