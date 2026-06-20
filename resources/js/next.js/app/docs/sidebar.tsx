import { useEffect, useState } from "react";
import { DOCS_BASE, nav } from "./nav";
import { Chevron, Icon } from "./icons";

const BRAND = "#4910ce";

function NavLink({
  slug,
  label,
  active,
  nested,
  onNavigate,
}: {
  slug: string;
  label: string;
  active: boolean;
  nested?: boolean;
  onNavigate: (slug: string) => void;
}) {
  return (
    <a
      href={`${DOCS_BASE}/${slug}`}
      onClick={(e) => {
        if (e.metaKey || e.ctrlKey || e.shiftKey) return;
        e.preventDefault();
        onNavigate(slug);
      }}
      aria-current={active ? "page" : undefined}
      className={[
        "block rounded-lg text-sm transition-colors",
        nested ? "py-1.5 pl-9 pr-3" : "px-3 py-2 font-medium",
        active
          ? "bg-purple-50 font-semibold"
          : "text-gray-600 hover:bg-gray-50 hover:text-gray-900",
      ].join(" ")}
      style={active ? { color: BRAND } : undefined}
    >
      {label}
    </a>
  );
}

function Group({
  label,
  icon,
  items,
  current,
  onNavigate,
}: {
  label: string;
  icon?: string;
  items: { slug: string; label: string }[];
  current: string;
  onNavigate: (slug: string) => void;
}) {
  const containsCurrent = items.some((i) => i.slug === current);
  const [open, setOpen] = useState(containsCurrent);

  // Auto-expand the group that holds the active page (e.g. after navigation).
  useEffect(() => {
    if (containsCurrent) setOpen(true);
  }, [containsCurrent]);

  return (
    <div>
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50"
      >
        <Icon name={icon} className="h-4 w-4 shrink-0 text-gray-400" />
        <span className="flex-1 text-left">{label}</span>
        <Chevron open={open} className="h-4 w-4 text-gray-400" />
      </button>
      {open && (
        <div className="mt-0.5 space-y-0.5">
          {items.map((i) => (
            <NavLink
              key={i.slug}
              slug={i.slug}
              label={i.label}
              active={current === i.slug}
              nested
              onNavigate={onNavigate}
            />
          ))}
        </div>
      )}
    </div>
  );
}

export function Sidebar({
  current,
  onNavigate,
}: {
  current: string;
  onNavigate: (slug: string) => void;
}) {
  return (
    <nav className="space-y-1">
      {nav.map((node) =>
        node.type === "item" ? (
          <a
            key={node.slug}
            href={`${DOCS_BASE}/${node.slug}`}
            onClick={(e) => {
              if (e.metaKey || e.ctrlKey || e.shiftKey) return;
              e.preventDefault();
              onNavigate(node.slug);
            }}
            aria-current={current === node.slug ? "page" : undefined}
            className={[
              "flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors",
              current === node.slug
                ? "bg-purple-50"
                : "text-gray-700 hover:bg-gray-50",
            ].join(" ")}
            style={current === node.slug ? { color: BRAND } : undefined}
          >
            <Icon name={node.icon} className="h-4 w-4 shrink-0 text-gray-400" />
            {node.label}
          </a>
        ) : (
          <Group
            key={node.label}
            label={node.label}
            icon={node.icon}
            items={node.items}
            current={current}
            onNavigate={onNavigate}
          />
        ),
      )}
    </nav>
  );
}
