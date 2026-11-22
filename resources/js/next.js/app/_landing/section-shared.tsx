import type { ReactNode } from "react";
import { SparkleIcon } from "./icons";

export function Container({ children, className = "" }: { children: ReactNode; className?: string }) {
  return <div className={`max-w-[1200px] mx-auto px-5 sm:px-7 ${className}`}>{children}</div>;
}

export function Eyebrow({ children, className = "" }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={`inline-flex items-center gap-2 text-[13px] font-semibold tracking-wider text-purple-700 bg-purple-50 px-3 py-1.5 rounded-full border border-purple-100 ${className}`}
    >
      {children}
    </div>
  );
}

export function SectionHead({
  eyebrowText,
  title,
  subtitle,
}: {
  eyebrowText: string;
  title: ReactNode;
  subtitle?: ReactNode;
}) {
  return (
    <div className="text-center max-w-[720px] mx-auto mb-14 reveal">
      <Eyebrow className="mb-[18px]">
        <SparkleIcon /> {eyebrowText}
      </Eyebrow>
      <h2 className="font-extrabold leading-[1.05] -tracking-[0.03em] text-[clamp(32px,4vw,52px)]">{title}</h2>
      {subtitle ? <p className="mt-3.5 text-ink-500 font-medium leading-[1.5] text-[clamp(16px,1.4vw,19px)]">{subtitle}</p> : null}
    </div>
  );
}
