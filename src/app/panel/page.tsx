import Link from "next/link";
import { format } from "date-fns";
import { pl } from "date-fns/locale";
import { getSession } from "@/lib/auth";
import { prisma } from "@/lib/db";

export default async function ParentOverviewPage() {
  const session = await getSession();
  if (!session) return null;

  const [children, upcoming] = await Promise.all([
    prisma.child.findMany({ where: { parentId: session.sub } }),
    prisma.enrollment.findMany({
      where: {
        parentId: session.sub,
        status: { in: ["PENDING", "CONFIRMED", "WAITLIST"] },
        session: { startsAt: { gte: new Date() } },
      },
      include: { child: true, session: { include: { classType: true } } },
      orderBy: { session: { startsAt: "asc" } },
      take: 5,
    }),
  ]);

  return (
    <div>
      <h1 className="text-2xl font-extrabold">Witaj, {session.name.split(" ")[0]} 👋</h1>

      <div className="mt-6 grid gap-4 sm:grid-cols-2">
        <Link
          href="/panel/dzieci"
          className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 hover:shadow-sm"
        >
          <p className="text-sm text-[var(--muted)]">Dzieci na koncie</p>
          <p className="text-3xl font-extrabold">{children.length}</p>
          <p className="mt-2 text-sm text-[var(--primary)]">Zarządzaj dziećmi →</p>
        </Link>
        <Link
          href="/kalendarz"
          className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 hover:shadow-sm"
        >
          <p className="text-sm text-[var(--muted)]">Zapisz na nowe zajęcia</p>
          <p className="text-3xl font-extrabold">📅</p>
          <p className="mt-2 text-sm text-[var(--primary)]">Otwórz kalendarz →</p>
        </Link>
      </div>

      <h2 className="mt-10 text-lg font-bold">Najbliższe zajęcia</h2>
      {upcoming.length === 0 ? (
        <p className="mt-2 text-sm text-[var(--muted)]">
          Brak nadchodzących zapisów.{" "}
          <Link href="/kalendarz" className="text-[var(--primary)] underline">
            Zapisz dziecko na zajęcia
          </Link>
          .
        </p>
      ) : (
        <ul className="mt-3 space-y-2">
          {upcoming.map((e) => (
            <li
              key={e.id}
              className="flex items-center justify-between rounded-xl border border-[var(--border)] bg-[var(--surface)] p-4 text-sm"
            >
              <div>
                <p className="font-semibold">
                  {e.session.classType.name} — {e.child.firstName} {e.child.lastName}
                </p>
                <p className="text-[var(--muted)]">
                  {format(e.session.startsAt, "EEEE d MMMM, HH:mm", { locale: pl })}
                </p>
              </div>
              {e.status === "WAITLIST" && (
                <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                  Lista rezerwowa
                </span>
              )}
              {e.status === "PENDING" && (
                <span className="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">
                  Oczekuje na potwierdzenie
                </span>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
