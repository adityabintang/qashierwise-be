import { ArrowLeft, Pencil, ExternalLink } from "lucide-react";
import { useEffect, useState } from "react";
import { postApi, errorMessage, type PostDetail } from "../../api";
import { useAuth } from "../../auth";
import { Link, navigate } from "../../router";
import { Badge, Button, Card, PageLoader } from "../../ui";
import { useToast } from "../../toast";
import { formatDate } from "../../format";

export function PostView({ id }: { id: number }) {
  const { user } = useAuth();
  const perms = user!.permissions.blog_post;
  const toast = useToast();
  const [post, setPost] = useState<PostDetail | null>(null);

  useEffect(() => {
    postApi
      .get(id)
      .then(setPost)
      .catch((err) => {
        toast.error(errorMessage(err, "Artikel tidak ditemukan"));
        navigate("/admin/posts");
      });
  }, [id, toast]);

  if (!post) return <PageLoader />;

  return (
    <div className="admin-fade-in">
      <div className="mb-6 flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Link
            to="/admin/posts"
            className="grid h-9 w-9 place-items-center rounded-xl text-ink-500 transition hover:bg-ink-100"
          >
            <ArrowLeft className="h-5 w-5" />
          </Link>
          <h1 className="text-2xl font-bold tracking-tight text-ink-900">Detail Artikel</h1>
        </div>
        <div className="flex items-center gap-2">
          <a href={`/blog/${post.slug}`} target="_blank" rel="noreferrer">
            <Button variant="secondary">
              <ExternalLink className="h-4 w-4" /> Lihat di Blog
            </Button>
          </a>
          {perms.update && (
            <Button onClick={() => navigate(`/admin/posts/${post.id}/edit`)}>
              <Pencil className="h-4 w-4" /> Edit
            </Button>
          )}
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          {post.featured_image && (
            <img
              src={post.featured_image_url}
              alt={post.title}
              className="h-64 w-full rounded-2xl object-cover shadow-[var(--shadow-card)]"
            />
          )}
          <Card className="p-6">
            <h2 className="text-xl font-bold text-ink-900">{post.title}</h2>
            {post.excerpt && <p className="mt-2 text-ink-500">{post.excerpt}</p>}
            <hr className="my-5 border-ink-100" />
            <div
              className="admin-prose text-ink-700"
              dangerouslySetInnerHTML={{ __html: post.content }}
            />
          </Card>
        </div>

        <div className="space-y-4">
          <Card className="space-y-3 p-5 text-sm">
            <Meta label="Status">
              <Badge color={post.status_color}>{post.status_label}</Badge>
            </Meta>
            <Meta label="Kategori">
              {post.category ? <Badge color="primary">{post.category.name}</Badge> : "—"}
            </Meta>
            <Meta label="Penulis">{post.author?.name ?? "—"}</Meta>
            <Meta label="Slug">
              <span className="font-mono text-xs text-ink-500">{post.slug}</span>
            </Meta>
            <Meta label="Publikasi">{formatDate(post.published_at, true)}</Meta>
            <Meta label="Dibuat">{formatDate(post.created_at, true)}</Meta>
            <Meta label="Diperbarui">{formatDate(post.updated_at, true)}</Meta>
          </Card>

          {post.tags.length > 0 && (
            <Card className="p-5">
              <p className="mb-2 text-sm font-semibold text-ink-700">Tag</p>
              <div className="flex flex-wrap gap-1.5">
                {post.tags.map((t) => (
                  <Badge key={t.id}>{t.name}</Badge>
                ))}
              </div>
            </Card>
          )}

          {(post.seo_title || post.seo_description) && (
            <Card className="space-y-2 p-5 text-sm">
              <p className="font-semibold text-ink-700">SEO</p>
              <Meta label="Title">{post.seo_title ?? "—"}</Meta>
              <Meta label="Description">{post.seo_description ?? "—"}</Meta>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}

function Meta({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex items-start justify-between gap-3">
      <span className="text-ink-400">{label}</span>
      <span className="text-right font-medium text-ink-700">{children}</span>
    </div>
  );
}
