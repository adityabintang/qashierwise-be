import { useEffect } from "react";
import { AuthProvider, useAuth } from "./auth";
import { ToastProvider } from "./toast";
import { matchPath, navigate, useLocation } from "./router";
import { Spinner } from "./ui";
import { Layout } from "./components/Layout";
import { Login } from "./pages/Login";
import { Dashboard } from "./pages/Dashboard";
import { PostList } from "./pages/posts/PostList";
import { PostForm } from "./pages/posts/PostForm";
import { PostView } from "./pages/posts/PostView";
import { CategoryList } from "./pages/categories/CategoryList";
import { TagList } from "./pages/tags/TagList";

function FullScreenLoader() {
  return (
    <div className="grid min-h-screen place-items-center">
      <Spinner className="h-8 w-8" />
    </div>
  );
}

function Routes() {
  const path = useLocation();

  // /admin/posts/create
  if (matchPath("/admin/posts/create", path)) return <PostForm />;

  // /admin/posts/:id/edit
  const edit = matchPath("/admin/posts/:id/edit", path);
  if (edit) return <PostForm id={Number(edit.id)} />;

  // /admin/posts/:id
  const view = matchPath("/admin/posts/:id", path);
  if (view) return <PostView id={Number(view.id)} />;

  if (matchPath("/admin/posts", path)) return <PostList />;
  if (matchPath("/admin/categories", path)) return <CategoryList />;
  if (matchPath("/admin/tags", path)) return <TagList />;
  if (matchPath("/admin", path)) return <Dashboard />;

  return <Dashboard />;
}

function Shell() {
  const { user, loading } = useAuth();
  const path = useLocation();
  const isLogin = !!matchPath("/admin/login", path);

  // Keep URL and auth state in sync (redirect on the edges).
  useEffect(() => {
    if (loading) return;
    if (!user && !isLogin) navigate("/admin/login", true);
    if (user && isLogin) navigate("/admin", true);
  }, [user, loading, isLogin]);

  if (loading) return <FullScreenLoader />;
  if (!user) return <Login />;
  if (isLogin) return <FullScreenLoader />; // brief, while redirect effect runs

  return (
    <Layout>
      <Routes />
    </Layout>
  );
}

export default function AdminApp() {
  return (
    <ToastProvider>
      <AuthProvider>
        <Shell />
      </AuthProvider>
    </ToastProvider>
  );
}
