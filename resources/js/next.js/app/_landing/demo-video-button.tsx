"use client";

import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { AnimatePresence, motion } from "motion/react";
import { XIcon } from "lucide-react";
import { cn } from "@/lib/utils";
import { PlayIcon } from "./icons";

const VIDEO_SRC = "https://www.youtube.com/embed/vv2j4apjqQc?si=kpmyx80UUIlwgGvQ";

function VideoOverlay({ onClose }: { onClose: () => void }) {
  useEffect(() => {
    const handler = (e: KeyboardEvent) => { if (e.key === "Escape") onClose(); };
    document.addEventListener("keydown", handler);
    return () => document.removeEventListener("keydown", handler);
  }, [onClose]);

  return createPortal(
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      onClick={onClose}
      className="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 backdrop-blur-md"
    >
      <motion.div
        initial={{ scale: 0.5, opacity: 0 }}
        animate={{ scale: 1, opacity: 1 }}
        exit={{ scale: 0.5, opacity: 0 }}
        transition={{ type: "spring", damping: 30, stiffness: 300 }}
        onClick={(e) => e.stopPropagation()}
        className="relative mx-4 aspect-video w-full max-w-4xl md:mx-0"
      >
        <button
          type="button"
          onClick={onClose}
          className="absolute -top-14 right-0 rounded-full bg-neutral-900/50 p-2 text-white ring-1 ring-white/20 backdrop-blur-md hover:bg-neutral-900/80 transition-colors"
          aria-label="Tutup video"
        >
          <XIcon className="size-5" />
        </button>
        <div className="size-full overflow-hidden rounded-2xl border-2 border-white">
          <iframe
            src={VIDEO_SRC}
            title="QashierWise Demo"
            className="size-full"
            allowFullScreen
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          />
        </div>
      </motion.div>
    </motion.div>,
    document.body
  );
}

export function DemoVideoButton({
  className,
  variant = "default",
}: {
  className?: string;
  variant?: "default" | "light" | "sm";
}) {
  const [isOpen, setIsOpen] = useState(false);

  const base = "inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-all";
  const variants = {
    default: "h-12 px-[22px] text-[15px] bg-white text-ink-900 border border-ink-200 hover:bg-ink-50 hover:border-ink-300",
    light:   "h-12 px-[22px] text-[15px] bg-white/10 text-white border border-white/20 backdrop-blur-md hover:bg-white/20 hover:border-white/30",
    sm:      "h-[38px] px-4 text-sm bg-white text-ink-900 border border-ink-200 hover:bg-ink-50 hover:border-ink-300",
  };

  return (
    <>
      <button
        type="button"
        onClick={() => setIsOpen(true)}
        className={cn(base, variants[variant], className)}
      >
        <PlayIcon /> Lihat Demo
      </button>

      <AnimatePresence>
        {isOpen && <VideoOverlay onClose={() => setIsOpen(false)} />}
      </AnimatePresence>
    </>
  );
}
