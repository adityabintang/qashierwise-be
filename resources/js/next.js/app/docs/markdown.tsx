import { type ComponentPropsWithoutRef } from "react";
import Markdown from "react-markdown";
import remarkGfm from "remark-gfm";

// Brand purple, matching the public docs / theme-color (#4910ce).
const BRAND = "#4910ce";

function slugifyHeading(children: React.ReactNode): string {
  const text = extractText(children);
  return text
    .toLowerCase()
    .replace(/[^\w\s-]/g, "")
    .trim()
    .replace(/\s+/g, "-");
}

function extractText(node: React.ReactNode): string {
  if (node == null) return "";
  if (typeof node === "string" || typeof node === "number") return String(node);
  if (Array.isArray(node)) return node.map(extractText).join("");
  if (typeof node === "object" && "props" in (node as never)) {
    return extractText((node as { props: { children?: React.ReactNode } }).props.children);
  }
  return "";
}

/**
 * Renders markdown with Tailwind-styled elements (no typography plugin needed).
 * Internal `/docs/...` links are intercepted for client-side navigation; external
 * links open in a new tab.
 */
export function MarkdownView({
  source,
  onNavigate,
}: {
  source: string;
  onNavigate: (slug: string) => void;
}) {
  return (
    <Markdown
      remarkPlugins={[remarkGfm]}
      components={{
        h1: ({ children }) => (
          <h1
            id={slugifyHeading(children)}
            className="scroll-mt-24 text-3xl font-bold tracking-tight text-gray-900 mb-4"
          >
            {children}
          </h1>
        ),
        h2: ({ children }) => (
          <h2
            id={slugifyHeading(children)}
            className="scroll-mt-24 mt-10 mb-3 border-b border-gray-100 pb-2 text-2xl font-semibold tracking-tight text-gray-900"
          >
            {children}
          </h2>
        ),
        h3: ({ children }) => (
          <h3
            id={slugifyHeading(children)}
            className="scroll-mt-24 mt-7 mb-2 text-lg font-semibold text-gray-900"
          >
            {children}
          </h3>
        ),
        h4: ({ children }) => (
          <h4 className="mt-6 mb-2 text-base font-semibold text-gray-800">{children}</h4>
        ),
        p: ({ children }) => (
          <p className="my-4 leading-7 text-gray-700">{children}</p>
        ),
        a: ({ href, children }) => {
          const url = href ?? "#";
          const isInternal = url.startsWith("/docs");
          if (isInternal) {
            return (
              <a
                href={url}
                onClick={(e) => {
                  if (e.metaKey || e.ctrlKey || e.shiftKey) return;
                  e.preventDefault();
                  const [path, hash] = url.replace(/^\/docs\/?/, "").split("#");
                  onNavigate(path || "overview");
                  if (hash) {
                    setTimeout(() => {
                      document.getElementById(hash)?.scrollIntoView({ behavior: "smooth" });
                    }, 60);
                  }
                }}
                className="font-medium underline decoration-purple-300 underline-offset-2 hover:opacity-80"
                style={{ color: BRAND }}
              >
                {children}
              </a>
            );
          }
          return (
            <a
              href={url}
              target={url.startsWith("http") ? "_blank" : undefined}
              rel="noopener noreferrer"
              className="font-medium underline decoration-purple-300 underline-offset-2 hover:opacity-80"
              style={{ color: BRAND }}
            >
              {children}
            </a>
          );
        },
        ul: ({ children }) => (
          <ul className="my-4 ml-5 list-disc space-y-2 text-gray-700 marker:text-gray-400">
            {children}
          </ul>
        ),
        ol: ({ children }) => (
          <ol className="my-4 ml-5 list-decimal space-y-2 text-gray-700 marker:text-gray-400 marker:font-medium">
            {children}
          </ol>
        ),
        li: ({ children }) => <li className="leading-7 pl-1">{children}</li>,
        strong: ({ children }) => (
          <strong className="font-semibold text-gray-900">{children}</strong>
        ),
        em: ({ children }) => <em className="italic">{children}</em>,
        blockquote: ({ children }) => (
          <blockquote
            className="my-5 rounded-r-lg border-l-4 bg-purple-50/60 py-1 pl-4 pr-3 text-gray-700 [&>p]:my-2"
            style={{ borderColor: BRAND }}
          >
            {children}
          </blockquote>
        ),
        code: ({ className, children, ...props }: ComponentPropsWithoutRef<"code">) => {
          const isBlock = /language-/.test(className ?? "");
          if (isBlock) {
            return (
              <code className={`${className ?? ""} block`} {...props}>
                {children}
              </code>
            );
          }
          return (
            <code
              className="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[0.85em] text-purple-700"
              {...props}
            >
              {children}
            </code>
          );
        },
        pre: ({ children }) => (
          <pre className="my-5 overflow-x-auto rounded-xl bg-gray-900 p-4 text-sm leading-6 text-gray-100">
            {children}
          </pre>
        ),
        hr: () => <hr className="my-8 border-gray-200" />,
        img: ({ src, alt }) => (
          <img
            src={typeof src === "string" ? src : undefined}
            alt={alt}
            className="my-5 rounded-xl border border-gray-200"
            loading="lazy"
          />
        ),
        table: ({ children }) => (
          <div className="my-6 overflow-x-auto rounded-xl border border-gray-200">
            <table className="w-full border-collapse text-sm">{children}</table>
          </div>
        ),
        thead: ({ children }) => <thead className="bg-gray-50">{children}</thead>,
        th: ({ children }) => (
          <th className="border-b border-gray-200 px-4 py-2.5 text-left font-semibold text-gray-900">
            {children}
          </th>
        ),
        td: ({ children }) => (
          <td className="border-b border-gray-100 px-4 py-2.5 align-top text-gray-700">
            {children}
          </td>
        ),
      }}
    >
      {source}
    </Markdown>
  );
}
