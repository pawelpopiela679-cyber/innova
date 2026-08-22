import Link from "next/link";
import { prisma } from "@/lib/db";
import { classTypeIcon } from "@/lib/class-type-icons";

export default async function ClassesPage() {
  const classTypes = await prisma.classType.findMany({
    orderBy: { createdAt: "asc" },
    include: { pricingTiers: { orderBy: { sortOrder: "asc" } } },
  });

  return (
    <div className="mx-auto max-w-4xl px-4 py-12">
      <h1 className="text-center font-heading text-3xl font-extrabold">
        Oferta <span className="text-[var(--coral)]">i cennik</span>
      </h1>
      <p className="mx-auto mt-2 max-w-xl text-center text-[var(--muted)]">
        Zajęcia odbywają się 1x w tygodniu. Pełny terminarz i wolne miejsca znajdziesz w{" "}
        <Link href="/kalendarz" className="text-[var(--coral)] underline">
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
            style={{ borderTopWidth: 4, borderTopColor: ct.color }}
          >
            <div className="flex items-center gap-2">
              <span className="text-2xl" aria-hidden>
                {classTypeIcon(ct.key)}
              </span>
              <h2 className="font-heading text-xl font-bold">{ct.name}</h2>
            </div>
            <p className="mt-3 text-[var(--foreground)]">{ct.description}</p>

            {ct.pricingTiers.length > 0 && (
              <div className="mt-4 overflow-x-auto">
                <table className="w-full min-w-[420px] border-collapse text-sm">
                  <thead>
                    <tr className="text-left text-[var(--muted)]">
                      {ct.pricingTiers.some((t) => t.label) && <th className="pb-2 pr-3 font-semibold">Wariant</th>}
                      <th className="pb-2 pr-3 font-semibold">Wiek</th>
                      <th className="pb-2 pr-3 font-semibold">Czas</th>
                      <th className="pb-2 font-semibold">Cena</th>
                    </tr>
                  </thead>
                  <tbody>
                    {ct.pricingTiers.map((tier) => (
                      <tr key={tier.id} className="border-t border-[var(--border)]">
                        {ct.pricingTiers.some((t) => t.label) && (
                          <td className="py-2 pr-3">{tier.label || ct.name}</td>
                        )}
                        <td className="py-2 pr-3 text-[var(--muted)]">{tier.ageLabel}</td>
                        <td className="py-2 pr-3 text-[var(--muted)]">{tier.durationMin} min</td>
                        <td className="py-2 font-semibold text-[var(--sage)]">
                          {tier.priceMonthly} zł / mies.
                          {tier.oneTimeFee != null && (
                            <span className="ml-1 font-normal text-[var(--muted)]">
                              (+ {tier.oneTimeFee} zł pakiet startowy, jednorazowo)
                            </span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <p className="mt-3 text-sm text-[var(--muted)]">
              Wiek uczestników: {ct.ageMin}–{ct.ageMax} lat
            </p>
            <Link
              href={`/kalendarz?classType=${ct.id}`}
              className="mt-4 inline-block rounded-full bg-[var(--sage)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
            >
              Zobacz terminy
            </Link>
          </section>
        ))}
      </div>

      <p className="mt-8 text-center text-sm text-[var(--muted)]">
        <span className="font-semibold text-[var(--foreground)]">Zniżki:</span> rodzeństwo −15% ·
        Karta Dużej Rodziny −10%
      </p>
    </div>
  );
}
