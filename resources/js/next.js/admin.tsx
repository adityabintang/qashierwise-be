import { createRoot } from "react-dom/client";
import "./app/admin/admin.css";

import AdminApp from "./app/admin/AdminApp";

// Dedicated Vite entry for the React admin (blog CMS). Kept separate from the
// public `main.tsx` bundle so the admin code never ships to landing/docs pages.
const el = document.getElementById("app");
if (el) {
  createRoot(el).render(<AdminApp />);
}
