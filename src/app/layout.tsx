import type { Metadata } from "next";
import "./globals.css";
import { Navbar } from "@/components/navbar";

export const metadata: Metadata = {
  title: "Pracownia Innova — zajęcia dla dzieci",
  description:
    "Zapisy na zajęcia online dla dzieci: robotyka, zajęcia kreatywne, teatralne i język angielski.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="pl" className="h-full antialiased">
      <body className="min-h-full flex flex-col">
        <Navbar />
        <main className="flex-1">{children}</main>
        <footer className="border-t border-[var(--border)] py-6 text-center text-sm text-[var(--muted)]">
          © {new Date().getFullYear()} Pracownia Innova — zajęcia kreatywne dla dzieci
        </footer>
      </body>
    </html>
  );
}
