import { ArrowLeft, ChevronDown, Save } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import {
  postApi,
  categoryApi,
  tagApi,
  errorMessage,
  type Category,
  type PostPayload,
  type PostStatus,
  type Tag,
} from "../../api";
import { Link, navigate } from "../../router";
import { Card, Field, Input, Select, Textarea, Button, PageLoader } from "../../ui";
import { ImageUpload } from "../../components/ImageUpload";
import { RichEditor } from "../../components/RichEditor";
import { useToast } from "../../toast";
import { fromLocalInput, slugify, toLocalInput } from "../../format";
import { cn } from "../../../../lib/utils";

type FormState = {
  title: string;
  slug: string;
  slugTouched: boolean;
  blog_category_id: string;
  excerpt: string;
  content: string;
  featured_image: string | null;
  featured_image_url: string | null;
  status: PostStatus;
  published_local: string;
  seo_title: string;
  seo_description: string;
  seo_image: string | null;
  seo_image_url: string | null;
  tags: number[];
};

const empty: FormState = {
  title: "",
  slug: "",
  slugTouched: false,
  blog_category_id: "",
  excerpt: "",
  content: "",
  featured_image: null,
  featured_image_url: null,
  status: "draft",
  published_local: "",
  seo_title: "",
  seo_description: "",
  seo_image: null,
  seo_image_url: null,
  tags: [],
};

export function PostForm({ id }: { id?: number }) {
  const editing = id !== undefined;
  const toast = useToast();

  const [form, setForm] = useState<FormState>(empty);
  const [categories, setCategories] = useState<Category[]>([]);
  const [tags, setTags] = useState<Tag[]>([]);
  const [loading, setLoading] = useState(editing);
  const [saving, setSaving] = useState(false);
  const [seoOpen, setSeoOpen] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((f) => ({ ...f, [key]: value }));

  useEffect(() => {
    Promise.all([categoryApi.all(), tagApi.all()])
      .then(([c, t]) => {
        setCategories(c);
        setTags(t);
      })
      .catch(() => {});
  }, []);

  useEffect(() => {
    if (!editing) return;
    postApi
      .get(id)
      .then((p) => {
        setForm({
          title: p.title,
          slug: p.slug,
          slugTouched: true,
          blog_category_id: p.blog_category_id ? String(p.blog_category_id) : "",
          excerpt: p.excerpt ?? "",
          content: p.content,
          featured_image: p.featured_image,
          featured_image_url: p.featured_image ? p.featured_image_url : null,
          status: p.status,
          published_local: toLocalInput(p.published_at),
          seo_title: p.seo_title ?? "",
          seo_description: p.seo_description ?? "",
          seo_image: p.seo_image,
          seo_image_url: p.seo_image ? p.seo_image_url : null,
          tags: p.tags.map((t) => t.id),
        });
      })
      .catch((err) => toast.error(errorMessage(err, "Gagal memuat artikel")))
      .finally(() => setLoading(false));
  }, [editing, id, toast]);

  const onTitle = (value: string) => {
    setForm((f) => ({
      ...f,
      title: value,
      slug: f.slugTouched ? f.slug : slugify(value),
    }));
  };

  const payload = useMemo<PostPayload>(
    () => ({
      title: form.title.trim(),
      slug: form.slug.trim(),
      blog_category_id: form.blog_category_id ? Number(form.blog_category_id) : null,
      excerpt: form.excerpt.trim() || null,
      content: form.content,
      featured_image: form.featured_image,
      status: form.status,
      published_at: fromLocalInput(form.published_local),
      seo_title: form.seo_title.trim() || null,
      seo_description: form.seo_description.trim() || null,
      seo_image: form.seo_image,
      tags: form.tags,
    }),
    [form]
  );

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    if (!payload.title) return setErrors({ title: "Judul wajib diisi" });
    if (!payload.content || payload.content === "<br>")
      return setErrors({ content: "Konten wajib diisi" });

    setSaving(true);
    try {
      const saved = editing
        ? await postApi.update(id, payload)
        : await postApi.create(payload);
      toast.success(editing ? "Artikel diperbarui" : "Artikel dibuat");
      navigate(`/admin/posts/${saved.id}`);
    } catch (err) {
      toast.error(errorMessage(err, "Gagal menyimpan artikel"));
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <PageLoader />;

  return (
    <form onSubmit={submit} className="admin-fade-in">
      <div className="mb-6 flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Link
            to={editing ? `/admin/posts/${id}` : "/admin/posts"}
            className="grid h-9 w-9 place-items-center rounded-xl text-ink-500 transition hover:bg-ink-100"
          >
            <ArrowLeft className="h-5 w-5" />
          </Link>
          <h1 className="text-2xl font-bold tracking-tight text-ink-900">
            {editing ? "Edit Artikel" : "Artikel Baru"}
          </h1>
        </div>
        <Button type="submit" loading={saving}>
          <Save className="h-4 w-4" /> Simpan
        </Button>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Main content */}
        <div className="space-y-6 lg:col-span-2">
          <Card className="space-y-4 p-5">
            <Field label="Judul" required error={errors.title}>
              <Input value={form.title} onChange={(e) => onTitle(e.target.value)} />
            </Field>
            <Field label="Slug" hint="URL artikel; otomatis dari judul">
              <Input
                value={form.slug}
                onChange={(e) => setForm((f) => ({ ...f, slug: e.target.value, slugTouched: true }))}
              />
            </Field>
            <Field label="Kategori">
              <Select
                value={form.blog_category_id}
                onChange={(e) => set("blog_category_id", e.target.value)}
              >
                <option value="">Tanpa kategori</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Ringkasan (excerpt)">
              <Textarea
                rows={3}
                value={form.excerpt}
                onChange={(e) => set("excerpt", e.target.value)}
              />
            </Field>
            <Field label="Konten" required error={errors.content}>
              <RichEditor value={form.content} onChange={(html) => set("content", html)} />
            </Field>
          </Card>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          <Card className="p-5">
            <h3 className="mb-3 text-sm font-semibold text-ink-700">Publikasi</h3>
            <div className="space-y-4">
              <Field label="Status">
                <Select
                  value={form.status}
                  onChange={(e) => set("status", e.target.value as PostStatus)}
                >
                  <option value="draft">Draft</option>
                  <option value="published">Published</option>
                  <option value="scheduled">Scheduled</option>
                </Select>
              </Field>
              {form.status !== "draft" && (
                <Field
                  label="Tanggal publikasi"
                  error={errors.published_at}
                  hint={
                    form.status === "scheduled"
                      ? "Harus di masa depan"
                      : "Tidak boleh melebihi waktu sekarang"
                  }
                >
                  <Input
                    type="datetime-local"
                    value={form.published_local}
                    onChange={(e) => set("published_local", e.target.value)}
                  />
                </Field>
              )}
              <Field label="Tag">
                <div className="flex flex-wrap gap-1.5">
                  {tags.length === 0 && (
                    <span className="text-xs text-ink-400">Belum ada tag</span>
                  )}
                  {tags.map((t) => {
                    const active = form.tags.includes(t.id);
                    return (
                      <button
                        key={t.id}
                        type="button"
                        onClick={() =>
                          set(
                            "tags",
                            active
                              ? form.tags.filter((x) => x !== t.id)
                              : [...form.tags, t.id]
                          )
                        }
                        className={cn(
                          "rounded-full px-3 py-1 text-xs font-medium transition",
                          active
                            ? "bg-purple-600 text-white"
                            : "bg-ink-100 text-ink-500 hover:bg-ink-200"
                        )}
                      >
                        {t.name}
                      </button>
                    );
                  })}
                </div>
              </Field>
            </div>
          </Card>

          <Card className="p-5">
            <h3 className="mb-3 text-sm font-semibold text-ink-700">Gambar Utama</h3>
            <ImageUpload
              type="featured"
              value={form.featured_image}
              previewUrl={form.featured_image_url}
              onChange={(path, url) =>
                setForm((f) => ({ ...f, featured_image: path, featured_image_url: url }))
              }
              helper="Maks 20 MB. Rasio landscape disarankan."
            />
          </Card>

          <Card className="p-5">
            <button
              type="button"
              onClick={() => setSeoOpen((o) => !o)}
              className="flex w-full items-center justify-between text-sm font-semibold text-ink-700"
            >
              SEO
              <ChevronDown className={cn("h-4 w-4 transition", seoOpen && "rotate-180")} />
            </button>
            {seoOpen && (
              <div className="mt-4 space-y-4">
                <Field label="SEO Title" hint={`${form.seo_title.length}/70`}>
                  <Input
                    maxLength={70}
                    value={form.seo_title}
                    onChange={(e) => set("seo_title", e.target.value)}
                  />
                </Field>
                <Field label="SEO Description" hint={`${form.seo_description.length}/160`}>
                  <Textarea
                    rows={3}
                    maxLength={160}
                    value={form.seo_description}
                    onChange={(e) => set("seo_description", e.target.value)}
                  />
                </Field>
                <Field label="OG Image">
                  <ImageUpload
                    type="seo"
                    value={form.seo_image}
                    previewUrl={form.seo_image_url}
                    onChange={(path, url) =>
                      setForm((f) => ({ ...f, seo_image: path, seo_image_url: url }))
                    }
                  />
                </Field>
              </div>
            )}
          </Card>
        </div>
      </div>
    </form>
  );
}
