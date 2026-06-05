import { Mail, Lock, Eye, EyeOff, Loader2 } from "lucide-react";
import { useState } from "react";
import { useAuth } from "../auth";
import { errorMessage } from "../api";
import { navigate } from "../router";

/*
 * Admin login. Styling intentionally preserved from the old Filament login
 * (glassmorphism card over the blog-cover background) — see admin.css.
 */
export function Login() {
  const { login } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(false);
  const [show, setShow] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await login(email, password, remember);
      navigate("/admin");
    } catch (err) {
      setError(errorMessage(err, "Email atau kata sandi salah"));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="admin-login">
      <form onSubmit={submit} className="admin-login-card admin-fade-in">
        <div className="mb-6 text-center">
          <img
            src="/images/logo-64.png"
            alt="QashierWise"
            className="mx-auto mb-3 h-12 w-12 rounded-2xl"
          />
          <h1 className="text-xl font-bold">QashierWise CMS</h1>
          <p className="mt-1 text-sm opacity-80">Masuk untuk mengelola konten</p>
        </div>

        {error && (
          <div className="mb-4 rounded-lg border border-white/20 bg-rose-500/20 px-3 py-2 text-sm text-white">
            {error}
          </div>
        )}

        <div className="space-y-3">
          <div>
            <label className="mb-1.5 block text-sm font-medium">Email</label>
            <div className="admin-login-field">
              <Mail className="h-4 w-4" />
              <input
                type="email"
                required
                autoComplete="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="admin@qashierwise.com"
              />
            </div>
          </div>

          <div>
            <label className="mb-1.5 block text-sm font-medium">Kata Sandi</label>
            <div className="admin-login-field">
              <Lock className="h-4 w-4" />
              <input
                type={show ? "text" : "password"}
                required
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
              />
              <button
                type="button"
                onClick={() => setShow((s) => !s)}
                className="text-white/70 transition hover:text-white"
              >
                {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            </div>
          </div>

          <label className="flex items-center gap-2 text-sm text-white/90">
            <input
              type="checkbox"
              checked={remember}
              onChange={(e) => setRemember(e.target.checked)}
              className="h-4 w-4 accent-purple-600"
            />
            Ingat saya
          </label>
        </div>

        <button type="submit" disabled={loading} className="admin-login-btn mt-6">
          {loading ? (
            <span className="inline-flex items-center gap-2">
              <Loader2 className="h-4 w-4 admin-spin" /> Memproses…
            </span>
          ) : (
            "Masuk"
          )}
        </button>
      </form>
    </div>
  );
}
