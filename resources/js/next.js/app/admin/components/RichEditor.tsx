import {
  Bold,
  Italic,
  Underline,
  Heading2,
  Heading3,
  List,
  ListOrdered,
  Quote,
  Link2,
  ImagePlus,
  Loader2,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { uploadApi, errorMessage } from "../api";
import { useToast } from "../toast";
import { cn } from "../../../lib/utils";

/*
 * Lightweight HTML rich-text editor built on contenteditable + execCommand.
 * Zero extra dependencies; output is HTML compatible with the public blog
 * renderer (which prints {!! $post->content !!}). Replaces Filament RichEditor.
 */
type Cmd = {
  icon: React.ComponentType<{ className?: string }>;
  title: string;
  run: (exec: (c: string, v?: string) => void) => void;
};

const groups: Cmd[][] = [
  [
    { icon: Bold, title: "Bold", run: (e) => e("bold") },
    { icon: Italic, title: "Italic", run: (e) => e("italic") },
    { icon: Underline, title: "Underline", run: (e) => e("underline") },
  ],
  [
    { icon: Heading2, title: "Heading 2", run: (e) => e("formatBlock", "<h2>") },
    { icon: Heading3, title: "Heading 3", run: (e) => e("formatBlock", "<h3>") },
    { icon: Quote, title: "Quote", run: (e) => e("formatBlock", "<blockquote>") },
  ],
  [
    { icon: List, title: "Bullet list", run: (e) => e("insertUnorderedList") },
    { icon: ListOrdered, title: "Numbered list", run: (e) => e("insertOrderedList") },
  ],
];

export function RichEditor({
  value,
  onChange,
  placeholder = "Tulis konten artikel…",
}: {
  value: string;
  onChange: (html: string) => void;
  placeholder?: string;
}) {
  const ref = useRef<HTMLDivElement>(null);
  const [uploading, setUploading] = useState(false);
  const toast = useToast();

  // Sync external value in only when it diverges (avoids caret jumps on typing).
  useEffect(() => {
    const el = ref.current;
    if (el && el.innerHTML !== value) el.innerHTML = value || "";
  }, [value]);

  const exec = (command: string, arg?: string) => {
    ref.current?.focus();
    document.execCommand(command, false, arg);
    emit();
  };

  const emit = () => {
    if (ref.current) onChange(ref.current.innerHTML);
  };

  const addLink = () => {
    const url = window.prompt("URL tautan:");
    if (url) exec("createLink", url);
  };

  const addImage = async () => {
    const input = document.createElement("input");
    input.type = "file";
    input.accept = "image/*";
    input.onchange = async () => {
      const file = input.files?.[0];
      if (!file) return;
      setUploading(true);
      try {
        const { url } = await uploadApi.upload(file, "content");
        exec("insertImage", url);
      } catch (err) {
        toast.error(errorMessage(err, "Gagal mengunggah gambar"));
      } finally {
        setUploading(false);
      }
    };
    input.click();
  };

  const btn =
    "grid h-8 w-8 place-items-center rounded-lg text-ink-500 transition hover:bg-ink-100 hover:text-ink-900";

  return (
    <div className="overflow-hidden rounded-xl ring-1 ring-ink-200 focus-within:ring-2 focus-within:ring-purple-400">
      <div className="flex flex-wrap items-center gap-1 border-b border-ink-100 bg-ink-50 px-2 py-1.5">
        {groups.map((group, gi) => (
          <div key={gi} className="flex items-center gap-0.5">
            {gi > 0 && <span className="mx-1 h-5 w-px bg-ink-200" />}
            {group.map((c) => (
              <button
                key={c.title}
                type="button"
                title={c.title}
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => c.run(exec)}
                className={btn}
              >
                <c.icon className="h-4 w-4" />
              </button>
            ))}
          </div>
        ))}
        <span className="mx-1 h-5 w-px bg-ink-200" />
        <button type="button" title="Tautan" onMouseDown={(e) => e.preventDefault()} onClick={addLink} className={btn}>
          <Link2 className="h-4 w-4" />
        </button>
        <button type="button" title="Gambar" onMouseDown={(e) => e.preventDefault()} onClick={addImage} className={btn} disabled={uploading}>
          {uploading ? <Loader2 className="h-4 w-4 admin-spin" /> : <ImagePlus className="h-4 w-4" />}
        </button>
      </div>
      <div
        ref={ref}
        contentEditable
        suppressContentEditableWarning
        onInput={emit}
        onBlur={emit}
        data-placeholder={placeholder}
        className={cn(
          "admin-editor admin-prose min-h-[18rem] max-h-[34rem] overflow-y-auto bg-white px-4 py-3 text-sm text-ink-900 outline-none"
        )}
      />
    </div>
  );
}
