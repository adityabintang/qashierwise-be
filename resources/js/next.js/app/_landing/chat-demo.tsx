"use client";

import { useEffect, useState } from "react";
import { SparkleIcon } from "./icons";

type Msg =
  | { from: "them" | "us"; text: string; ai?: boolean; delay: number }
  | { from: "qris"; delay: number };

const script: Msg[] = [
  { from: "them", text: "Halo, masih buka? Mau pesan untuk 4 orang malam ini jam 7", delay: 0 },
  { from: "us", ai: true, text: "Halo! 👋 Tentu, kami masih buka. Untuk 4 orang jam 19:00 masih tersedia di lantai 2. Mau saya bookingkan?", delay: 1400 },
  { from: "them", text: "Iya tolong, atas nama Ibu Sari", delay: 3200 },
  { from: "us", ai: true, text: "Siap Ibu Sari ✨ Reservasi tercatat. Saya kirim QRIS untuk DP Rp50.000 ya 🧾", delay: 4800 },
  { from: "qris", delay: 6400 },
];

const QRBlock = () => {
  const cells =
    "1111111010101111111100000101001100000110111011010001011011001011011110010110011101110110010101001110110110100100110100000010110101000101110000010011111111101010111111110000000110010001010111101101000010110110110111100100100010110110001111101110111000110000111101100100010101101100000000100100110011101";
  const size = 13;
  return (
    <div className="grid w-[88px] h-[88px]" style={{ gridTemplateColumns: `repeat(${size}, 1fr)`, gap: 0 }}>
      {[...cells.slice(0, size * size)].map((c, i) => (
        <div key={i} className="aspect-square" style={{ background: c === "1" ? "#0B0A0F" : "#fff" }} />
      ))}
    </div>
  );
};

export function ChatDemo() {
  const [visible, setVisible] = useState<number[]>([]);
  const [typing, setTyping] = useState(false);

  useEffect(() => {
    let timers: ReturnType<typeof setTimeout>[] = [];
    const loop = () => {
      setVisible([]);
      setTyping(false);
      script.forEach((m, i) => {
        if (m.from === "us" || m.from === "qris") {
          timers.push(setTimeout(() => setTyping(true), Math.max(0, m.delay - 700)));
        }
        timers.push(
          setTimeout(() => {
            setTyping(false);
            setVisible((v) => [...v, i]);
          }, m.delay)
        );
      });
      timers.push(setTimeout(loop, 9500));
    };
    loop();
    return () => timers.forEach(clearTimeout);
  }, []);

  return (
    <div className="w-full h-full bg-white rounded-[44px] overflow-hidden relative flex flex-col">
      <div className="absolute top-4 left-1/2 -translate-x-1/2 w-[100px] h-7 bg-ink-900 rounded-full z-[5]" />
      <div className="flex justify-between items-center px-7 pt-6 pb-2 text-sm font-semibold">
        <span>9:41</span>
        <div className="flex gap-1 items-center">
          <svg width="16" height="10" viewBox="0 0 16 10" fill="currentColor">
            <rect x="0" y="6" width="3" height="4" rx="0.5" />
            <rect x="4" y="4" width="3" height="6" rx="0.5" />
            <rect x="8" y="2" width="3" height="8" rx="0.5" />
            <rect x="12" y="0" width="3" height="10" rx="0.5" />
          </svg>
          <svg width="14" height="10" viewBox="0 0 14 10" fill="currentColor">
            <path d="M7 2C4.5 2 2.5 3 1 4.5l1 1C3.3 4.5 5 3.7 7 3.7s3.7.8 5 1.8l1-1C11.5 3 9.5 2 7 2zm0 3.7C5.7 5.7 4.5 6.3 3.7 7l1 1C5.3 7.5 6.1 7 7 7s1.7.5 2.3 1l1-1c-.8-.7-2-1.3-3.3-1.3zM7 8.3c-.7 0-1.2.5-1.2 1.2s.5 1.2 1.2 1.2 1.2-.5 1.2-1.2S7.7 8.3 7 8.3z" />
          </svg>
          <svg width="24" height="11" viewBox="0 0 24 11">
            <rect x="0.5" y="0.5" width="20" height="10" rx="2.5" fill="none" stroke="currentColor" />
            <rect x="2" y="2" width="17" height="7" rx="1.5" fill="currentColor" />
            <rect x="21" y="3.5" width="1.5" height="4" rx="0.5" fill="currentColor" />
          </svg>
        </div>
      </div>

      <div className="bg-[#075E54] text-white pl-4 pr-4 pt-2 pb-3 flex items-center gap-2.5">
        <div className="w-9 h-9 rounded-full bg-gradient-to-br from-[#25D366] to-[#128C7E] grid place-items-center font-bold text-white text-sm">Q</div>
        <div>
          <div className="text-sm font-semibold leading-tight">QashierWise Resto</div>
          <div className="text-[11px] opacity-80">● online · AI agent</div>
        </div>
        <div className="ml-auto flex gap-3.5 opacity-70">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17 10.5V7a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3.5l4 4v-11l-4 4z" /></svg>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 15.5c-1.2 0-2.5-.2-3.6-.6-.3-.1-.7 0-1 .2l-2.2 2.2c-2.8-1.4-5.2-3.7-6.6-6.6l2.2-2.2c.3-.3.3-.7.2-1-.4-1.1-.6-2.4-.6-3.6 0-.5-.5-1-1-1H4c-.5 0-1 .5-1 1 0 9.4 7.6 17 17 17 .5 0 1-.5 1-1v-3.5c0-.6-.5-1-1-1z" /></svg>
        </div>
      </div>

      <div className="chat-pattern flex-1 px-3 py-3.5 flex flex-col gap-1.5 overflow-hidden relative">
        {script.map((m, i) => {
          if (!visible.includes(i)) return null;
          const baseBubble =
            "max-w-[78%] py-1.5 px-2.5 rounded-[14px] text-[13px] leading-[1.35] relative shadow-[0_1px_1px_rgba(0,0,0,0.08)] animate-bubble-in";
          if (m.from === "qris") {
            return (
              <div key={i} className={`${baseBubble} self-end !bg-white rounded-tr-[4px] !p-1.5 !max-w-[200px]`}>
                <div className="p-1.5 bg-white rounded-[10px]">
                  <QRBlock />
                  <div className="text-[11px] font-bold mt-1">Rp 50.000</div>
                  <div className="text-[9px] text-ink-500">QRIS · Berlaku 15 menit</div>
                </div>
                <span className="block text-[9px] text-black/40 text-right mt-0.5">19:24 ✓✓</span>
              </div>
            );
          }
          const isUs = m.from === "us";
          return (
            <div
              key={i}
              className={`${baseBubble} ${
                isUs ? "self-end bg-[#DCF8C6] rounded-tr-[4px]" : "self-start bg-white rounded-tl-[4px]"
              }`}
            >
              {m.ai && (
                <div className="inline-flex items-center gap-1 text-[9px] font-bold text-purple-700 bg-purple-50 rounded px-1.5 py-px mb-1 tracking-[0.04em]">
                  <SparkleIcon /> AI
                </div>
              )}
              {m.text}
              <span className="block text-[9px] text-black/40 text-right mt-0.5">
                {isUs ? `19:2${i + 2} ✓✓` : `19:2${i + 2}`}
              </span>
            </div>
          );
        })}
        {typing && (
          <div className="self-start bg-white py-2.5 px-3 rounded-[14px] rounded-tl-[4px] flex gap-1">
            <span className="w-1.5 h-1.5 rounded-full bg-ink-300 animate-typing-dot" />
            <span className="w-1.5 h-1.5 rounded-full bg-ink-300 animate-typing-dot [animation-delay:0.15s]" />
            <span className="w-1.5 h-1.5 rounded-full bg-ink-300 animate-typing-dot [animation-delay:0.3s]" />
          </div>
        )}
      </div>
    </div>
  );
}
