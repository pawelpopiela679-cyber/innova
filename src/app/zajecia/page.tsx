import Link from "next/link";
import { prisma } from "@/lib/db";

export default async function ClassesPage() {
  const classTypes = await prisma.classType.findMany({ orderBy: { createdAt: "asc" } });

  return (
    <div className="mx-auto max-w-4xl px-4 py-12">
      <h1 className="text-3xl font-extrabold">Rodzaje zajęć</h1>
      <p className="mt-2 text-[var(--muted)]">
        Na start prowadzimy cztery grupy zajęć online. Każda odbywa się cyklicznie —
        pełny terminarz i wolne miejsca znajdziesz w{" "}
        <Link href="/kalendarz" className="text-[var(--primary)] underline">
          kalendarzu
        </Link>
        .
      </p>

      <div className="mt-8 space-y-6">
        {classTypes.map((ct) => (
          <section
            key={ct.id}
            id={ct.key}
            className="scroll-mt-20 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6"
          >
            <div className="flex items-center gap-3">
              <span
                className="h-3 w-3 rounded-full"
                style={{ backgroundColor: ct.color }}
                aria-hidden
              />
              <h2 className="text-xl font-bold">{ct.name}</h2>
            </div>
            <p className="mt-3 text-[var(--foreground)]">{ct.description}</p>
            <p className="mt-3 text-sm text-[var(--muted)]">
              Wiek uczestników: {ct.ageMin}–{ct.ageMax} lat
            </p>
            <Link
              href={`/kalendarz?classType=${ct.id}`}
              className="mt-4 inline-block rounded-full bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-[var(--primary-foreground)] hover:opacity-90"
            >
              Zobacz terminy
            </Link>
          </section>
        ))}
      </div>
    </div>
  );
}
