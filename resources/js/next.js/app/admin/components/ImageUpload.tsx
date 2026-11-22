import { ImagePlus, Loader2, Trash2 } from "lucide-react";
import { useRef, useState } from "react";
import { uploadApi, errorMessage, type UploadType } from "../api";
import { useToast } from "../toast";

/*
 * Single-image upload. Stores the file on r2 via the API and reports back the
 * storage `path` (what the model persists) plus the public URL for preview.
 */
export function ImageUpload({
  type,
  value,
  previewUrl,
  onChange,
  helper,
}: {
  type: UploadType;
  value: string | null;
  previewUrl: string | null;
  onChange: (path: string | null, url: string | null) => void;
  helper?: string;
}) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);
  const toast = useToast();

  const handleFile = async (file: File | undefined) => {
    if (!file) return;
    setUploading(true);
    try {
      const { path, url } = await uploadApi.upload(file, type);
      onChange(path, url);
    } catch (err) {
      toast.error(errorMessage(err, "Gagal mengunggah gambar"));
    } finally {
      setUploading(false);
      if (inputRef.current) inputRef.current.value = "";
    }
  };

  return (
    <div>
      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={(e) => handleFile(e.target.files?.[0])}
      />
      {previewUrl ? (
        <div className="group relative overflow-hidden rounded-xl ring-1 ring-ink-200">
          <img src={previewUrl} alt="" className="h-44 w-full object-cover" />
          <div className="absolute inset-0 flex items-center justify-center gap-2 bg-ink-900/40 opacity-0 transition group-hover:opacity-100">
            <button
              type="button"
              onClick={() => inputRef.current?.click()}
              className="rounded-lg bg-white/90 px-3 py-1.5 text-sm font-medium text-ink-700"
            >
              Ganti
            </button>
            <button
              type="button"
              onClick={() => onChange(null, null)}
              className="rounded-lg bg-ios-red px-3 py-1.5 text-sm font-medium text-white"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          </div>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          disabled={uploading}
          className="flex h-44 w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-ink-200 text-ink-400 transition hover:border-purple-300 hover:text-purple-500"
        >
          {uploading ? (
            <Loader2 className="h-6 w-6 admin-spin" />
          ) : (
            <ImagePlus className="h-6 w-6" />
          )}
          <span className="text-sm">{uploading ? "Mengunggah…" : "Unggah gambar"}</span>
        </button>
      )}
      {helper && <p className="mt-1.5 text-xs text-ink-400">{helper}</p>}
    </div>
  );
}
