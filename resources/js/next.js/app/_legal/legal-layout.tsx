import { Nav } from "@/app/_landing/nav";
import { Footer } from "@/app/_landing/footer";
import { RevealObserver } from "@/app/_landing/reveal-observer";

export function LegalLayout({ children }: { children: React.ReactNode }) {
  return (
    <>
      <Nav />
      <main>{children}</main>
      <Footer />
      <RevealObserver />
    </>
  );
}
