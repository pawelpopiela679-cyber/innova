import { LeafSprig } from "@/components/decor";

export function Footer() {
  return (
    <footer className="relative overflow-hidden border-t border-[var(--border)] bg-[var(--surface)]">
      <LeafSprig
        className="pointer-events-none absolute -bottom-6 left-6 h-24 w-10 rotate-[20deg] opacity-50"
        color="var(--sage)"
      />
      <LeafSprig
        className="pointer-events-none absolute -bottom-8 right-8 h-28 w-12 -rotate-[15deg] opacity-40"
        color="var(--coral)"
      />

      <div className="relative mx-auto max-w-6xl px-4 py-8 text-sm">
        <div className="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-center text-[var(--foreground)]">
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
        <p className="mt-4 text-center text-[var(--muted)]">
          © {new Date().getFullYear()} INNOVA — Pracownia kreatywno-edukacyjna
        </p>
      </div>
    </footer>
  );
}
