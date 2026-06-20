import axios, { type AxiosInstance } from "axios";

/*
 * Axios client for the admin API. Uses Sanctum SPA cookie auth (same-origin,
 * withCredentials). `api/*` is CSRF-exempt server-side, so no token dance.
 */
export const http: AxiosInstance = axios.create({
  baseURL: "/api/admin",
  withCredentials: true,
  headers: {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  },
});

export type CrudPerms = {
  view: boolean;
  create: boolean;
  update: boolean;
  delete: boolean;
};

export type AdminUser = {
  id: number;
  name: string;
  email: string;
  is_super_admin: boolean;
  permissions: {
    blog_post: CrudPerms;
    blog_category: CrudPerms;
    blog_tag: CrudPerms;
  };
};

export type PostStatus = "draft" | "published" | "scheduled";

export type CategoryLite = { id: number; name: string; slug: string };
export type TagLite = { id: number; name: string; slug: string };

export type PostListItem = {
  id: number;
  title: string;
  slug: string;
  status: PostStatus;
  status_label: string;
  status_color: string;
  excerpt: string | null;
  featured_image: string | null;
  featured_image_url: string;
  category: CategoryLite | null;
  author: { id: number; name: string } | null;
  published_at: string | null;
  created_at: string | null;
};

export type PostDetail = PostListItem & {
  blog_category_id: number | null;
  content: string;
  seo_title: string | null;
  seo_description: string | null;
  seo_image: string | null;
  seo_image_url: string;
  tags: TagLite[];
  updated_at: string | null;
};

export type Category = {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  is_active: boolean;
  seo_title: string | null;
  seo_description: string | null;
  posts_count: number;
  created_at: string | null;
  updated_at: string | null;
};

export type Tag = {
  id: number;
  name: string;
  slug: string;
  posts_count: number;
  created_at: string | null;
  updated_at: string | null;
};

export type Paginated<T> = {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export type DashboardData = {
  stats: {
    total_posts: number;
    published_posts: number;
    draft_posts: number;
    scheduled_posts: number;
    total_categories: number;
    total_tags: number;
  };
  posts_per_month: { label: string; count: number }[];
  popular_categories: { name: string; count: number }[];
  recent_posts: {
    id: number;
    title: string;
    category: string | null;
    status: PostStatus;
    status_color: string;
    published_at: string | null;
    created_at: string | null;
  }[];
};

export type UploadType = "featured" | "seo" | "content";

// --- Auth ---
export const authApi = {
  me: () => http.get<{ data: AdminUser }>("/auth/me").then((r) => r.data.data),
  login: (email: string, password: string, remember = false) =>
    http
      .post<{ data: AdminUser }>("/auth/login", { email, password, remember })
      .then((r) => r.data.data),
  logout: () => http.post("/auth/logout"),
};

// --- Dashboard ---
export const dashboardApi = {
  stats: () =>
    http.get<{ data: DashboardData }>("/dashboard/stats").then((r) => r.data.data),
};

// --- Uploads ---
export const uploadApi = {
  upload: (file: File, type: UploadType) => {
    const form = new FormData();
    form.append("file", file);
    form.append("type", type);
    return http
      .post<{ data: { path: string; url: string } }>("/uploads", form)
      .then((r) => r.data.data);
  },
};

type PostQuery = {
  search?: string;
  status?: string;
  category?: string | number;
  sort?: string;
  direction?: "asc" | "desc";
  page?: number;
  per_page?: number;
};

export type PostPayload = {
  title: string;
  slug: string;
  blog_category_id: number | null;
  excerpt: string | null;
  content: string;
  featured_image: string | null;
  status: PostStatus;
  published_at: string | null;
  seo_title: string | null;
  seo_description: string | null;
  seo_image: string | null;
  tags: number[];
};

export const postApi = {
  list: (params: PostQuery) =>
    http.get<Paginated<PostListItem>>("/posts", { params }).then((r) => r.data),
  get: (id: number | string) =>
    http.get<{ data: PostDetail }>(`/posts/${id}`).then((r) => r.data.data),
  create: (payload: PostPayload) =>
    http.post<{ data: PostDetail }>("/posts", payload).then((r) => r.data.data),
  update: (id: number, payload: PostPayload) =>
    http.put<{ data: PostDetail }>(`/posts/${id}`, payload).then((r) => r.data.data),
  remove: (id: number) => http.delete(`/posts/${id}`),
  bulkRemove: (ids: number[]) => http.delete("/posts/bulk", { data: { ids } }),
};

export type CategoryPayload = {
  name: string;
  slug?: string;
  description: string | null;
  is_active: boolean;
  seo_title: string | null;
  seo_description: string | null;
};

export const categoryApi = {
  list: (params: { search?: string; page?: number } = {}) =>
    http.get<Paginated<Category>>("/categories", { params }).then((r) => r.data),
  all: () =>
    http
      .get<{ data: Category[] }>("/categories", { params: { all: 1 } })
      .then((r) => r.data.data),
  get: (id: number | string) =>
    http.get<{ data: Category }>(`/categories/${id}`).then((r) => r.data.data),
  create: (payload: CategoryPayload) =>
    http.post<{ data: Category }>("/categories", payload).then((r) => r.data.data),
  update: (id: number, payload: CategoryPayload) =>
    http.put<{ data: Category }>(`/categories/${id}`, payload).then((r) => r.data.data),
  remove: (id: number) => http.delete(`/categories/${id}`),
};

export type TagPayload = { name: string; slug?: string };

export const tagApi = {
  list: (params: { search?: string; page?: number } = {}) =>
    http.get<Paginated<Tag>>("/tags", { params }).then((r) => r.data),
  all: () =>
    http.get<{ data: Tag[] }>("/tags", { params: { all: 1 } }).then((r) => r.data.data),
  get: (id: number | string) =>
    http.get<{ data: Tag }>(`/tags/${id}`).then((r) => r.data.data),
  create: (payload: TagPayload) =>
    http.post<{ data: Tag }>("/tags", payload).then((r) => r.data.data),
  update: (id: number, payload: TagPayload) =>
    http.put<{ data: Tag }>(`/tags/${id}`, payload).then((r) => r.data.data),
  remove: (id: number) => http.delete(`/tags/${id}`),
};

/** Pull a human message out of an axios error (Laravel validation or generic). */
export function errorMessage(err: unknown, fallback = "Something went wrong"): string {
  if (axios.isAxiosError(err)) {
    const data = err.response?.data as
      | { message?: string; errors?: Record<string, string[]> }
      | undefined;
    if (data?.errors) {
      const first = Object.values(data.errors)[0];
      if (first?.[0]) return first[0];
    }
    if (data?.message) return data.message;
  }
  return fallback;
}
