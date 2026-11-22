import { About } from "./_landing/about";
import { CTABand } from "./_landing/cta-band";
import { Dashboard } from "./_landing/dashboard";
import { FAQ } from "./_landing/faq";
import { Features } from "./_landing/features";
import { Footer } from "./_landing/footer";
import { Hero } from "./_landing/hero";
import { HowItWorks } from "./_landing/how-it-works";
import { Integrations } from "./_landing/integrations";
import { Nav } from "./_landing/nav";
import { Pricing } from "./_landing/pricing";
import { RevealObserver } from "./_landing/reveal-observer";


export default function LandingPage() {
  return (
    <>
      <Nav />
      <Hero />
      <HowItWorks />
      <Features />
      <Dashboard />
      <Integrations />
      <Pricing />
      <About />
      <FAQ />
      <CTABand />
      <Footer />
      <RevealObserver />
    </>
  );
}
