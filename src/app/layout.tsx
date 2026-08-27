import type { Metadata } from "next";
import { Fredoka, Quicksand, Caveat, Nunito } from "next/font/google";
import "./globals.css";
import { Navbar } from "@/components/navbar";
import { Footer } from "@/components/footer";
import { getTheme } from "@/lib/theme";

const fredoka = Fredoka({
  subsets: ["latin-ext"],
  weight: ["500", "600", "700"],
  variable: "--font-heading",
});

const quicksand = Quicksand({
  subsets: ["latin-ext"],
  weight: ["600", "700"],
  variable: "--font-logo",
});

const caveat = Caveat({
  subsets: ["latin-ext"],
  weight: ["600", "700"],
  variable: "--font-script",
});

const nunito = Nunito({
  subsets: ["latin-ext"],
  weight: ["400", "600", "700", "800"],
  variable: "--font-body",
});

export const metadata: Metadata = {
  title: "INNOVA — Pracownia kreatywno-edukacyjna",
  description:
    "Miejsce rozwoju dla Twojego dziecka. Zajęcia z angielskiego, scenicznych, robotyki, kreatywne, matematyki i eksperymentatorium w Czechowicach-Dziedzicach i online.",
};

export default async function RootLayout({ children }: LayoutProps<"/">) {
  const theme = await getTheme();
  // Overrides the static defaults in globals.css with whatever the master
  // admin picked in /admin/wyglad. Every other token (the "-soft" tints,
  // etc.) is computed from these via color-mix(), so it all stays coherent.
  const themeCss = `:root {
    --background: ${theme.background};
    --foreground: ${theme.foreground};
    --surface: ${theme.surface};
    --border: ${theme.border};
    --primary: ${theme.primary};
    --sage: ${theme.primary};
    --sage-light: ${theme.primaryLight};
    --coral: ${theme.accent};
    --mustard: ${theme.gold};
    --muted: ${theme.muted};
  }`;

  return (
    <html
      lang="pl"
      className={`${fredoka.variable} ${quicksand.variable} ${caveat.variable} ${nunito.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col">
        <style dangerouslySetInnerHTML={{ __html: themeCss }} />
        <Navbar />
        <main className="flex-1">{children}</main>
        <Footer />
      </body>
    </html>
  );
}
