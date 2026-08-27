import Link from "next/link";
import { prisma } from "@/lib/db";

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  const pendingCount = await prisma.enrollment.count({ where: { status: "PENDING" } });

  return (
    <div className="mx-auto max-w-6xl px-4 py-10">
      <nav className="mb-8 flex flex-wrap gap-2 rounded-full border border-[var(--border)] bg-[var(--surface)] p-1 text-sm w-fit">
        <Link href="/admin/zapisy" className="flex items-center gap-1.5 rounded-full px-4 py-1.5 hover:bg-[var(--background)]">
          Zgłoszenia
          {pendingCount > 0 && (
            <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-[var(--coral)] px-1 text-xs font-bold text-white">
              {pendingCount}
            </span>
          )}
        </Link>
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
