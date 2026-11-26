import { Plus, Search, FileText, Eye, Pencil, Trash2 } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  postApi,
  categoryApi,
  errorMessage,
  type PostListItem,
  type CategoryLite,
} from "../../api";
import { useAuth } from "../../auth";
import { Link, navigate } from "../../router";
import { PageHeader } from "../../components/Layout";
import { DataTable, Pagination, type Column } from "../../components/DataTable";
import { ConfirmDialog } from "../../components/Modal";
import { Badge, Button, EmptyState, Input, Select } from "../../ui";
import { useToast } from "../../toast";
import { formatDate } from "../../format";
import { postStatusLabel, tr } from "../../locale";

export function PostList() {
  const { user } = useAuth();
  const perms = user!.permissions.blog_post;
  const toast = useToast();

  const [rows, setRows] = useState<PostListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [categories, setCategories] = useState<CategoryLite[]>([]);

  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [category, setCategory] = useState("");
  const [page, setPage] = useState(1);
  const [sort, setSort] = useState("created_at");
  const [direction, setDirection] = useState<"asc" | "desc">("desc");

  const [selected, setSelected] = useState<number[]>([]);
  const [deleteTarget, setDeleteTarget] = useState<PostListItem | "bulk" | null>(null);
  const [deleting, setDeleting] = useState(false);

  const debounce = useRef<number | undefined>(undefined);

  const load = useCallback(() => {
    setLoading(true);
    postApi
      .list({ search, status, category, page, sort, direction, per_page: 10 })
      .then((res) => {
        setRows(res.data);
        setMeta(res.meta);
        setSelected([]);
      })
      .catch((err) => toast.error(errorMessage(err, tr("Gagal memuat artikel", "Failed to load posts"))))
      .finally(() => setLoading(false));
  }, [search, status, category, page, sort, direction, toast]);

  useEffect(() => {
    window.clearTimeout(debounce.current);
    debounce.current = window.setTimeout(load, search ? 350 : 0);
    return () => window.clearTimeout(debounce.current);
  }, [load, search]);

  useEffect(() => {
    categoryApi.all().then((c) => setCategories(c)).catch(() => {});
  }, []);

  const toggleSort = (key: string) => {
    if (sort === key) setDirection((d) => (d === "asc" ? "desc" : "asc"));
    else {
      setSort(key);
      setDirection("asc");
    }
    setPage(1);
  };

  const confirmDelete = async () => {
    setDeleting(true);
    try {
      if (deleteTarget === "bulk") {
        await postApi.bulkRemove(selected);
        toast.success(tr(`${selected.length} artikel dihapus`, `${selected.length} posts deleted`));
      } else if (deleteTarget) {
        await postApi.remove(deleteTarget.id);
        toast.success(tr("Artikel dihapus", "Post deleted"));
      }
      setDeleteTarget(null);
      load();
    } catch (err) {
      toast.error(errorMessage(err, tr("Gagal menghapus", "Failed to delete")));
    } finally {
      setDeleting(false);
    }
  };

  const columns: Column<PostListItem>[] = [
    {
      key: "title",
      header: tr("Judul", "Title"),
      sortable: true,
      render: (p) => (
        <div className="flex items-center gap-3">
          <img
            src={p.featured_image_url}
            alt=""
            className="h-10 w-10 shrink-0 rounded-lg object-cover"
          />
          <div className="min-w-0">
            <p className="truncate font-medium text-ink-900">{p.title}</p>
            <p className="truncate text-xs text-ink-400">{p.author?.name ?? "—"}</p>
          </div>
        </div>
      ),
    },
    {
      key: "category",
      header: tr("Kategori", "Category"),
      render: (p) => (p.category ? <Badge color="primary">{p.category.name}</Badge> : "—"),
    },
    {
      key: "status",
      header: "Status",
      sortable: true,
      render: (p) => <Badge color={p.status_color}>{postStatusLabel(p.status)}</Badge>,
    },
    {
      key: "published_at",
      header: tr("Publikasi", "Publication"),
      sortable: true,
      render: (p) => (
        <span className="text-ink-500">{formatDate(p.published_at, true)}</span>
      ),
    },
    {
      key: "actions",
      header: "",
      className: "text-right",
      render: (p) => (
        <div className="flex items-center justify-end gap-1">
          <Link
            to={`/admin/posts/${p.id}`}
            className="grid h-8 w-8 place-items-center rounded-lg text-ink-400 transition hover:bg-ink-100 hover:text-ink-700"
          >
            <Eye className="h-4 w-4" />
          </Link>
          {perms.update && (
            <Link
              to={`/admin/posts/${p.id}/edit`}
              className="grid h-8 w-8 place-items-center rounded-lg text-ink-400 transition hover:bg-purple-100 hover:text-purple-600"
            >
              <Pencil className="h-4 w-4" />
            </Link>
          )}
          {perms.delete && (
            <button
              onClick={() => setDeleteTarget(p)}
              className="grid h-8 w-8 place-items-center rounded-lg text-ink-400 transition hover:bg-rose-100 hover:text-ios-red"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="admin-fade-in">
      <PageHeader
        title={tr("Artikel", "Posts")}
        description={tr("Kelola artikel blog", "Manage blog posts")}
        action={
          perms.create && (
            <Button onClick={() => navigate("/admin/posts/create")}>
              <Plus className="h-4 w-4" /> {tr("Artikel Baru", "New Post")}
            </Button>
          )
        }
      />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div className="relative min-w-[14rem] flex-1">
          <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-300" />
          <Input
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
            placeholder={tr("Cari artikel…", "Search posts…")}
            className="pl-9"
          />
        </div>
        <Select
          value={status}
          onChange={(e) => {
            setStatus(e.target.value);
            setPage(1);
          }}
          className="w-40"
        >
          <option value="">{tr("Semua status", "All statuses")}</option>
          <option value="published">{postStatusLabel("published")}</option>
          <option value="draft">Draft</option>
          <option value="scheduled">{postStatusLabel("scheduled")}</option>
        </Select>
        <Select
          value={category}
          onChange={(e) => {
            setCategory(e.target.value);
            setPage(1);
          }}
          className="w-44"
        >
          <option value="">{tr("Semua kategori", "All categories")}</option>
          {categories.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </Select>
      </div>

      {perms.delete && selected.length > 0 && (
        <div className="mb-3 flex items-center justify-between rounded-xl bg-purple-50 px-4 py-2.5 text-sm">
          <span className="font-medium text-purple-700">{selected.length} {tr("dipilih", "selected")}</span>
          <Button size="sm" variant="danger" onClick={() => setDeleteTarget("bulk")}>
            <Trash2 className="h-4 w-4" /> {tr("Hapus", "Delete")}
          </Button>
        </div>
      )}

      <DataTable
        columns={columns}
        data={rows}
        loading={loading}
        selectable={perms.delete}
        selectedIds={selected}
        onToggleAll={(c) => setSelected(c ? rows.map((r) => r.id) : [])}
        onToggleRow={(id, c) =>
          setSelected((s) => (c ? [...s, id] : s.filter((x) => x !== id)))
        }
        sort={sort}
        direction={direction}
        onSort={toggleSort}
        empty={
          <EmptyState
            icon={<FileText className="h-10 w-10" />}
            title={tr("Belum ada artikel", "No posts yet")}
            description={tr("Mulai tulis artikel pertama untuk blog Anda.", "Write your first blog post.")}
            action={
              perms.create && (
                <Button onClick={() => navigate("/admin/posts/create")}>
                  <Plus className="h-4 w-4" /> {tr("Artikel Baru", "New Post")}
                </Button>
              )
            }
          />
        }
      />

      <Pagination
        page={meta.current_page}
        lastPage={meta.last_page}
        total={meta.total}
        onPage={setPage}
      />

      <ConfirmDialog
        open={deleteTarget !== null}
        message={
          deleteTarget === "bulk"
            ? tr(`Hapus ${selected.length} artikel terpilih? Tindakan ini tidak dapat dibatalkan.`, `Delete ${selected.length} selected posts? This cannot be undone.`)
            : tr("Hapus artikel ini? Tindakan ini tidak dapat dibatalkan.", "Delete this post? This cannot be undone.")
        }
        loading={deleting}
        onConfirm={confirmDelete}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  );
}
