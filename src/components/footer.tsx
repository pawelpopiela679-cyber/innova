import Link from "next/link";
import { Logo } from "@/components/logo";
import { PaperPlaneDoodle, HeartDoodle } from "@/components/decor";

export function Footer() {
  return (
    <footer className="relative mt-16">
      <div
        className="torn-edge-top relative overflow-hidden pt-10 pb-8"
        style={{ backgroundColor: "var(--sage-soft)" }}
      >
        <div className="mx-auto max-w-6xl px-4 text-sm">
          <div className="flex flex-col items-center gap-1 text-center">
            <Logo size="sm" />
            <span className="text-xs font-medium text-[var(--muted)]">
              Pracownia kreatywno-edukacyjna
            </span>
          </div>

          <div className="mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-center text-[var(--foreground)]">
            <span className="flex items-center gap-1.5">
              <span aria-hidden>📍</span> ul. Kolejowa, Czechowice-Dziedzice
            </span>
            <a href="tel:+48570250363" className="flex items-center gap-1.5 hover:text-[var(--coral)]">
              <span aria-hidden>📞</span> 570 250 363
            </a>
            <a
              href="https://facebook.com/innova.pracownia"
              className="flex items-center gap-1.5 hover:text-[var(--coral)]"
            >
              <span aria-hidden>📘</span> fb /innova.pracownia
            </a>
            <a
              href="https://instagram.com/innova_pracownia"
              className="flex items-center gap-1.5 hover:text-[var(--coral)]"
            >
              <span aria-hidden>📷</span> ig /innova_pracownia
            </a>
            <a href="https://innova-pracownia.pl" className="flex items-center gap-1.5 hover:text-[var(--coral)]">
              <span aria-hidden>🌐</span> www.innova-pracownia.pl
            </a>
          </div>

          <div className="mt-6 flex flex-wrap items-center justify-center gap-x-3 gap-y-2 text-center font-heading text-sm font-bold text-[var(--ink)]">
            <Link href="/" className="hover:underline">Odkrywaj</Link>
            <span aria-hidden>•</span>
            <Link href="/zajecia" className="hover:underline">Twórz</Link>
            <span aria-hidden>•</span>
            <Link href="/zapisy" className="hover:underline">Rośnij</Link>
            <HeartDoodle className="h-3.5 w-3.5" />
          </div>

          <div className="mt-4 flex items-center justify-center gap-2 text-[var(--muted)]">
            <PaperPlaneDoodle className="h-4 w-4" />
            <p className="text-center">
              © {new Date().getFullYear()} INNOVA — Pracownia kreatywno-edukacyjna · Kreatywne dzieci to lepszy świat!
            </p>
          </div>
        </div>
      </div>
    </footer>
  );
}
