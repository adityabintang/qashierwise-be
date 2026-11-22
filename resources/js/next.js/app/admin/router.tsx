import { useCallback, useEffect, useState } from "react";

/*
 * Tiny history-based router (no react-router dependency). All admin routes live
 * under /admin, are rendered client-side, and fall back to the server only on
 * full page loads (the Laravel catch-all serves the SPA host for any /admin/*).
 */

const NAV_EVENT = "admin:navigate";

export function navigate(to: string, replace = false): void {
  if (replace) {
    window.history.replaceState({}, "", to);
  } else {
    window.history.pushState({}, "", to);
  }
  window.dispatchEvent(new Event(NAV_EVENT));
}

export function useLocation(): string {
  const [path, setPath] = useState(() => window.location.pathname);

  useEffect(() => {
    const update = () => setPath(window.location.pathname);
    window.addEventListener("popstate", update);
    window.addEventListener(NAV_EVENT, update);
    return () => {
      window.removeEventListener("popstate", update);
      window.removeEventListener(NAV_EVENT, update);
    };
  }, []);

  return path;
}

type LinkProps = React.AnchorHTMLAttributes<HTMLAnchorElement> & { to: string };

export function Link({ to, onClick, ...rest }: LinkProps) {
  const handle = useCallback(
    (e: React.MouseEvent<HTMLAnchorElement>) => {
      // Let modified clicks / new-tab behave natively.
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      e.preventDefault();
      onClick?.(e);
      if (window.location.pathname !== to) navigate(to);
    },
    [to, onClick]
  );
  return <a href={to} onClick={handle} {...rest} />;
}

/**
 * Match `/admin/posts/:id/edit` style patterns. Returns extracted params or null.
 */
export function matchPath(
  pattern: string,
  pathname: string
): Record<string, string> | null {
  const pParts = pattern.split("/").filter(Boolean);
  const uParts = pathname.split("/").filter(Boolean);
  if (pParts.length !== uParts.length) return null;

  const params: Record<string, string> = {};
  for (let i = 0; i < pParts.length; i++) {
    const p = pParts[i];
    const u = uParts[i];
    if (p.startsWith(":")) {
      params[p.slice(1)] = decodeURIComponent(u);
    } else if (p !== u) {
      return null;
    }
  }
  return params;
}
