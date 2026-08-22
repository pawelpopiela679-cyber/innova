import Link from "next/link";

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="mx-auto max-w-6xl px-4 py-10">
      <nav className="mb-8 flex gap-2 rounded-full border border-[var(--border)] bg-[var(--surface)] p-1 text-sm w-fit">
        <Link href="/admin" className="rounded-full px-4 py-1.5 hover:bg-[var(--background)]">
          Dostępność terminów
        </Link>
        <Link
          href="/admin/zajecia/nowe"
          className="rounded-full px-4 py-1.5 hover:bg-[var(--background)]"
        >
          + Nowe zajęcia
        </Link>
      </nav>
      {children}
    </div>
  );
}
