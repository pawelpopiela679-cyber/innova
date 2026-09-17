import Link from "next/link";

export default function ParentDashboardLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="mx-auto max-w-4xl px-4 py-10">
      <nav className="mb-8 flex gap-2 rounded-full border border-[var(--border)] bg-[var(--surface)] p-1 text-sm w-fit">
        <Link href="/panel" className="rounded-full px-4 py-1.5 hover:bg-[var(--background)]">
          Przegląd
        </Link>
        <Link href="/panel/dzieci" className="rounded-full px-4 py-1.5 hover:bg-[var(--background)]">
          Moje dzieci
        </Link>
        <Link href="/panel/zapisy" className="rounded-full px-4 py-1.5 hover:bg-[var(--background)]">
          Moje zapisy
        </Link>
        <Link href="/kalendarz" className="rounded-full px-4 py-1.5 hover:bg-[var(--background)]">
          Kalendarz zajęć
        </Link>
      </nav>
      {children}
    </div>
  );
}
