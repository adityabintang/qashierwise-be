import { Plus, Search, Tags as TagsIcon, Pencil, Trash2 } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";
import { tagApi, errorMessage, type Tag, type TagPayload } from "../../api";
import { useAuth } from "../../auth";
import { PageHeader } from "../../components/Layout";
import { DataTable, Pagination, type Column } from "../../components/DataTable";
import { ConfirmDialog, Modal } from "../../components/Modal";
import { Button, EmptyState, Field, Input } from "../../ui";
import { useToast } from "../../toast";

export function TagList() {
  const { user } = useAuth();
  const perms = user!.permissions.blog_tag;
  const toast = useToast();

  const [rows, setRows] = useState<Tag[]>([]);
  const [loading, setLoading] = useState(true);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);

  const [editing, setEditing] = useState<Tag | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ name: "", slug: "" });
  const [saving, setSaving] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<Tag | null>(null);
  const [deleting, setDeleting] = useState(false);

  const debounce = useRef<number | undefined>(undefined);

  const load = useCallback(() => {
    setLoading(true);
    tagApi
      .list({ search, page })
      .then((res) => {
        setRows(res.data);
        setMeta(res.meta);
      })
      .catch((err) => toast.error(errorMessage(err, "Gagal memuat tag")))
      .finally(() => setLoading(false));
  }, [search, page, toast]);

  useEffect(() => {
    window.clearTimeout(debounce.current);
    debounce.current = window.setTimeout(load, search ? 350 : 0);
    return () => window.clearTimeout(debounce.current);
  }, [load, search]);

  const openCreate = () => {
    setEditing(null);
    setForm({ name: "", slug: "" });
    setShowForm(true);
  };

  const openEdit = (t: Tag) => {
    setEditing(t);
    setForm({ name: t.name, slug: t.slug });
    setShowForm(true);
  };

  const save = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    const payload: TagPayload = {
      name: form.name.trim(),
      slug: form.slug.trim() || undefined,
    };
    try {
      if (editing) {
        await tagApi.update(editing.id, payload);
        toast.success("Tag diperbarui");
      } else {
        await tagApi.create(payload);
        toast.success("Tag dibuat");
      }
      setShowForm(false);
      load();
    } catch (err) {
      toast.error(errorMessage(err, "Gagal menyimpan tag"));
    } finally {
      setSaving(false);
    }
  };

  const confirmDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await tagApi.remove(deleteTarget.id);
      toast.success("Tag dihapus");
      setDeleteTarget(null);
      load();
    } catch (err) {
      toast.error(errorMessage(err, "Gagal menghapus tag"));
    } finally {
      setDeleting(false);
    }
  };

  const columns: Column<Tag>[] = [
    {
      key: "name",
      header: "Nama",
      render: (t) => (
        <div>
          <p className="font-medium text-ink-900">{t.name}</p>
          <p className="font-mono text-xs text-ink-400">{t.slug}</p>
        </div>
      ),
    },
    {
      key: "posts_count",
      header: "Artikel",
      render: (t) => <span className="text-ink-600">{t.posts_count}</span>,
    },
    {
      key: "actions",
      header: "",
      className: "text-right",
      render: (t) => (
        <div className="flex items-center justify-end gap-1">
          {perms.update && (
            <button
              onClick={() => openEdit(t)}
              className="grid h-8 w-8 place-items-center rounded-lg text-ink-400 transition hover:bg-purple-100 hover:text-purple-600"
            >
              <Pencil className="h-4 w-4" />
            </button>
          )}
          {perms.delete && (
            <button
              onClick={() => setDeleteTarget(t)}
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
        title="Tag"
        description="Kelola tag artikel"
        action={
          perms.create && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> Tag Baru
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
          placeholder="Cari tag…"
          className="pl-9"
        />
      </div>

      <DataTable
        columns={columns}
        data={rows}
        loading={loading}
        empty={
          <EmptyState
            icon={<TagsIcon className="h-10 w-10" />}
            title="Belum ada tag"
            action={
              perms.create && (
                <Button onClick={openCreate}>
                  <Plus className="h-4 w-4" /> Tag Baru
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
        title={editing ? "Edit Tag" : "Tag Baru"}
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
        message={`Hapus tag "${deleteTarget?.name}"?`}
        loading={deleting}
        onConfirm={confirmDelete}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  );
}
