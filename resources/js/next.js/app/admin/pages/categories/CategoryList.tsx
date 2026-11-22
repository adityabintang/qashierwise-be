import { Plus, Search, FolderTree, Pencil, Trash2 } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import {
  categoryApi,
  errorMessage,
  type Category,
  type CategoryPayload,
} from "../../api";
import { useAuth } from "../../auth";
import { PageHeader } from "../../components/Layout";
import { DataTable, Pagination, type Column } from "../../components/DataTable";
import { ConfirmDialog, Modal } from "../../components/Modal";
import { Badge, Button, EmptyState, Field, Input, Textarea, Toggle } from "../../ui";
import { useToast } from "../../toast";

const emptyForm = {
  name: "",
  slug: "",
  description: "",
  is_active: true,
  seo_title: "",
  seo_description: "",
};

export function CategoryList() {
  const { user } = useAuth();
  const perms = user!.permissions.blog_category;
  const toast = useToast();

  const [rows, setRows] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);

  const [editing, setEditing] = useState<Category | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<Category | null>(null);
  const [deleting, setDeleting] = useState(false);

  const debounce = useRef<number | undefined>(undefined);

  const load = useCallback(() => {
    setLoading(true);
    categoryApi
      .list({ search, page })
      .then((res) => {
        setRows(res.data);
        setMeta(res.meta);
      })
      .catch((err) => toast.error(errorMessage(err, "Gagal memuat kategori")))
      .finally(() => setLoading(false));
  }, [search, page, toast]);

  useEffect(() => {
    window.clearTimeout(debounce.current);
    debounce.current = window.setTimeout(load, search ? 350 : 0);
    return () => window.clearTimeout(debounce.current);
  }, [load, search]);

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm);
    setShowForm(true);
  };

  const openEdit = (c: Category) => {
    setEditing(c);
    setForm({
      name: c.name,
      slug: c.slug,
      description: c.description ?? "",
      is_active: c.is_active,
      seo_title: c.seo_title ?? "",
      seo_description: c.seo_description ?? "",
    });
    setShowForm(true);
  };

  const save = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    const payload: CategoryPayload = {
      name: form.name.trim(),
      slug: form.slug.trim() || undefined,
      description: form.description.trim() || null,
      is_active: form.is_active,
      seo_title: form.seo_title.trim() || null,
      seo_description: form.seo_description.trim() || null,
    };
    try {
      if (editing) {
        await categoryApi.update(editing.id, payload);
        toast.success("Kategori diperbarui");
      } else {
        await categoryApi.create(payload);
        toast.success("Kategori dibuat");
      }
      setShowForm(false);
      load();
    } catch (err) {
      toast.error(errorMessage(err, "Gagal menyimpan kategori"));
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await categoryApi.remove(deleteTarget.id);
      toast.success("Kategori dihapus");
      setDeleteTarget(null);
      load();
    } catch (err) {
      toast.error(errorMessage(err, "Gagal menghapus kategori"));
    } finally {
      setDeleting(false);
    }
  };

  const columns: Column<Category>[] = [
    {
      key: "name",
      header: "Nama",
      render: (c) => (
        <div>
          <p className="font-medium text-ink-900">{c.name}</p>
          <p className="font-mono text-xs text-ink-400">{c.slug}</p>
        </div>
      ),
    },
    {
      key: "is_active",
      header: "Status",
      render: (c) =>
        c.is_active ? (
          <Badge color="success">Aktif</Badge>
        ) : (
          <Badge color="gray">Nonaktif</Badge>
        ),
    },
    {
      key: "posts_count",
      header: "Artikel",
      render: (c) => <span className="text-ink-600">{c.posts_count}</span>,
    },
    {
      key: "actions",
      header: "",
      className: "text-right",
      render: (c) => (
        <div className="flex items-center justify-end gap-1">
          {perms.update && (
            <button
              onClick={() => openEdit(c)}
              className="grid h-8 w-8 place-items-center rounded-lg text-ink-400 transition hover:bg-purple-100 hover:text-purple-600"
            >
              <Pencil className="h-4 w-4" />
            </button>
          )}
          {perms.delete && (
            <button
              onClick={() => setDeleteTarget(c)}
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
        title="Kategori"
        description="Kelola kategori artikel"
        action={
          perms.create && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> Kategori Baru
            </Button>
          )
        }
      />

      <div className="relative mb-4 max-w-sm">
        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-300" />
        <Input
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setPage(1);
          }}
          placeholder="Cari kategori…"
          className="pl-9"
        />
      </div>

      <DataTable
        columns={columns}
        data={rows}
        loading={loading}
        empty={
          <EmptyState
            icon={<FolderTree className="h-10 w-10" />}
            title="Belum ada kategori"
            action={
              perms.create && (
                <Button onClick={openCreate}>
                  <Plus className="h-4 w-4" /> Kategori Baru
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

      <Modal
        open={showForm}
        onClose={() => setShowForm(false)}
        title={editing ? "Edit Kategori" : "Kategori Baru"}
      >
        <form onSubmit={save} className="space-y-4">
          <Field label="Nama" required>
            <Input
              value={form.name}
              onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
              autoFocus
            />
          </Field>
          <Field label="Slug" hint="Opsional; otomatis dari nama">
            <Input
              value={form.slug}
              onChange={(e) => setForm((f) => ({ ...f, slug: e.target.value }))}
            />
          </Field>
          <Field label="Deskripsi">
            <Textarea
              rows={3}
              value={form.description}
              onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
            />
          </Field>
          <Toggle
            checked={form.is_active}
            onChange={(v) => setForm((f) => ({ ...f, is_active: v }))}
            label="Aktif"
          />
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="secondary" onClick={() => setShowForm(false)}>
              Batal
            </Button>
            <Button type="submit" loading={saving}>
              Simpan
            </Button>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={deleteTarget !== null}
        message={`Hapus kategori "${deleteTarget?.name}"? Artikel terkait tidak ikut terhapus.`}
        loading={deleting}
        onConfirm={confirmDelete}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  );
}
