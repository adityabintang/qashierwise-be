import { ArrowDown, ArrowUp } from "lucide-react";
import { Spinner } from "../ui";
import { cn } from "../../../lib/utils";

export type Column<T> = {
  key: string;
  header: string;
  render: (row: T) => React.ReactNode;
  className?: string;
  sortable?: boolean;
};

export function DataTable<T extends { id: number }>({
  columns,
  data,
  loading,
  empty,
  selectable,
  selectedIds,
  onToggleAll,
  onToggleRow,
  sort,
  direction,
  onSort,
}: {
  columns: Column<T>[];
  data: T[];
  loading?: boolean;
  empty?: React.ReactNode;
  selectable?: boolean;
  selectedIds?: number[];
  onToggleAll?: (checked: boolean) => void;
  onToggleRow?: (id: number, checked: boolean) => void;
  sort?: string;
  direction?: "asc" | "desc";
  onSort?: (key: string) => void;
}) {
  const allChecked = selectable && data.length > 0 && selectedIds?.length === data.length;

  if (!loading && data.length === 0 && empty) {
    return <>{empty}</>;
  }

  return (
    <div className="overflow-hidden rounded-2xl bg-white shadow-[var(--shadow-card)] ring-1 ring-ink-100">
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm">
          <thead>
            <tr className="border-b border-ink-100 text-xs font-semibold tracking-wide text-ink-400 uppercase">
              {selectable && (
                <th className="w-10 px-4 py-3">
                  <input
                    type="checkbox"
                    className="h-4 w-4 accent-purple-600"
                    checked={!!allChecked}
                    onChange={(e) => onToggleAll?.(e.target.checked)}
                  />
                </th>
              )}
              {columns.map((col) => (
                <th key={col.key} className={cn("px-4 py-3", col.className)}>
                  {col.sortable && onSort ? (
                    <button
                      onClick={() => onSort(col.key)}
                      className="inline-flex items-center gap-1 transition hover:text-ink-700"
                    >
                      {col.header}
                      {sort === col.key &&
                        (direction === "asc" ? (
                          <ArrowUp className="h-3 w-3" />
                        ) : (
                          <ArrowDown className="h-3 w-3" />
                        ))}
                    </button>
                  ) : (
                    col.header
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {loading ? (
              <tr>
                <td
                  colSpan={columns.length + (selectable ? 1 : 0)}
                  className="px-4 py-16 text-center"
                >
                  <Spinner className="mx-auto h-6 w-6" />
                </td>
              </tr>
            ) : (
              data.map((row) => (
                <tr key={row.id} className="transition hover:bg-ink-50/60">
                  {selectable && (
                    <td className="px-4 py-3">
                      <input
                        type="checkbox"
                        className="h-4 w-4 accent-purple-600"
                        checked={selectedIds?.includes(row.id) ?? false}
                        onChange={(e) => onToggleRow?.(row.id, e.target.checked)}
                      />
                    </td>
                  )}
                  {columns.map((col) => (
                    <td key={col.key} className={cn("px-4 py-3 text-ink-700", col.className)}>
                      {col.render(row)}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export function Pagination({
  page,
  lastPage,
  total,
  onPage,
}: {
  page: number;
  lastPage: number;
  total: number;
  onPage: (p: number) => void;
}) {
  if (lastPage <= 1) return null;
  return (
    <div className="mt-4 flex items-center justify-between text-sm text-ink-400">
      <span>{total} item</span>
      <div className="flex items-center gap-1">
        <button
          disabled={page <= 1}
          onClick={() => onPage(page - 1)}
          className="rounded-lg px-3 py-1.5 font-medium text-ink-600 transition hover:bg-ink-100 disabled:opacity-40"
        >
          Sebelumnya
        </button>
        <span className="px-2 font-medium text-ink-700">
          {page} / {lastPage}
        </span>
        <button
          disabled={page >= lastPage}
          onClick={() => onPage(page + 1)}
          className="rounded-lg px-3 py-1.5 font-medium text-ink-600 transition hover:bg-ink-100 disabled:opacity-40"
        >
          Berikutnya
        </button>
      </div>
    </div>
  );
}
