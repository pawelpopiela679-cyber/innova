import { Navbar } from "@/components/navbar";
import { Footer } from "@/components/footer";

/**
 * Chrome for the account/admin side of the app (login, registration,
 * parent + staff panels, custom pages) — the functional navbar/footer.
 * The public marketing pages (home, o nas, zajęcia, grafik, zapisy,
 * kontakt, aktualności) render full-bleed on the studio's own mockup
 * images instead and skip this chrome.
 */
export default function SiteLayout({ children }: { children: React.ReactNode }) {
  return (
    <>
      <Navbar />
      <main className="flex-1">{children}</main>
      <Footer />
    </>
  );
}
