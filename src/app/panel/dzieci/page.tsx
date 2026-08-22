import { format } from "date-fns";
import { pl } from "date-fns/locale";
import { getSession } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { createChildAction, deleteChildAction } from "@/lib/actions/child-actions";

export default async function ChildrenPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string; added?: string; removed?: string }>;
}) {
  const sp = await searchParams;
  const session = await getSession();
  if (!session) return null;

  const children = await prisma.child.findMany({
    where: { parentId: session.sub },
    orderBy: { firstName: "asc" },
  });

  return (
    <div>
      <h1 className="text-2xl font-extrabold">Moje dzieci</h1>

      {sp.error && (
        <p className="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{sp.error}</p>
      )}
      {sp.added && (
        <p className="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
          Dziecko zostało dodane.
        </p>
      )}
      {sp.removed && (
        <p className="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
          Dziecko zostało usunięte.
        </p>
      )}

      <div className="mt-6 space-y-3">
        {children.length === 0 && (
          <p className="text-sm text-[var(--muted)]">
            Nie masz jeszcze dodanych dzieci — dodaj pierwsze poniżej.
          </p>
        )}
        {children.map((c) => (
          <div
            key={c.id}
            className="flex items-center justify-between rounded-xl border border-[var(--border)] bg-[var(--surface)] p-4"
          >
            <div>
              <p className="font-semibold">
                {c.firstName} {c.lastName}
              </p>
              <p className="text-sm text-[var(--muted)]">
                ur. {format(c.birthDate, "d MMMM yyyy", { locale: pl })}
              </p>
              {c.notes && <p className="mt-1 text-sm">{c.notes}</p>}
            </div>
            <form action={deleteChildAction}>
              <input type="hidden" name="childId" value={c.id} />
              <button
                type="submit"
                className="rounded-full border border-[var(--border)] px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
              >
                Usuń
              </button>
            </form>
          </div>
        ))}
      </div>

      <div className="mt-8 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6">
        <h2 className="font-bold">Dodaj dziecko</h2>
        <form action={createChildAction} className="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label htmlFor="firstName" className="text-sm font-medium">
              Imię
            </label>
            <input
              id="firstName"
              name="firstName"
              required
              className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
            />
          </div>
          <div>
            <label htmlFor="lastName" className="text-sm font-medium">
              Nazwisko
            </label>
            <input
              id="lastName"
              name="lastName"
              required
              className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
            />
          </div>
          <div>
            <label htmlFor="birthDate" className="text-sm font-medium">
              Data urodzenia
            </label>
            <input
              id="birthDate"
              name="birthDate"
              type="date"
              required
              className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
            />
          </div>
          <div>
            <label htmlFor="notes" className="text-sm font-medium">
              Uwagi (opcjonalnie)
            </label>
            <input
              id="notes"
              name="notes"
              placeholder="np. alergie, potrzeby specjalne"
              className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
            />
          </div>
          <div className="sm:col-span-2">
            <button
              type="submit"
              className="rounded-full bg-[var(--primary)] px-5 py-2.5 font-semibold text-[var(--primary-foreground)] hover:opacity-90"
            >
              Dodaj dziecko
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
