import { createRoot } from "react-dom/client";
import "./app/globals.css";

import LandingPage from "./app/page";
import PrivacyPolicy from "./app/privacy-policy/page";
import TermsOfService from "./app/terms-of-service/page";
import RefundPolicy from "./app/refund-policy/page";
import DocsPage from "./app/docs/page";

// The blade host sets window.__PAGE__ to choose which page to render. This lets
// every public React page share a single Vite entry (one bundle, code-split).
const pages: Record<string, React.ComponentType> = {
  landing: LandingPage,
  privacy: PrivacyPolicy,
  terms: TermsOfService,
  refund: RefundPolicy,
  docs: DocsPage,
};

const name = (window as unknown as { __PAGE__?: string }).__PAGE__ ?? "landing";
const Page = pages[name] ?? LandingPage;

const el = document.getElementById("app");
if (el) {
  createRoot(el).render(<Page />);
}
