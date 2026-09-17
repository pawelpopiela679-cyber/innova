import Link from "next/link";
import { prisma } from "@/lib/db";
import { classTypeIcon } from "@/lib/class-type-icons";
import { NotebookCard, SectionHeading } from "@/components/scrapbook";
import { LightbulbDoodle, StarDoodle } from "@/components/decor";

const TAB_COLORS = [
  "var(--pill-pink)",
  "var(--pill-mint)",
  "var(--pill-purple)",
  "var(--pill-orange)",
  "var(--pill-blue)",
  "var(--pill-teal)",
];

export default async function ClassesPage() {
  const classTypes = await prisma.classType.findMany({
    orderBy: { createdAt: "asc" },
    include: { pricingTiers: { orderBy: { sortOrder: "asc" } } },
  });

  return (
    <div className="mx-auto max-w-6xl px-4 py-12">
      <div className="relative">
        <StarDoodle className="absolute -left-2 top-0 hidden h-8 w-8 sm:block" />
        <LightbulbDoodle className="absolute -right-2 top-0 hidden h-10 w-10 sm:block" />
        <SectionHeading
          eyebrow="Zajęcia, które rozwijają pasje!"
          title="Zajęcia"
          subtitle="Twórcze, rozwijające i pełne dobrej energii zajęcia"
          highlight="var(--pill-blue)"
        />
      </div>

      <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {classTypes.map((ct, i) => (
          <NotebookCard
            key={ct.id}
            id={ct.key}
            className="scroll-mt-24"
            tab={{ label: ct.name, color: TAB_COLORS[i % TAB_COLORS.length] }}
          >
            <div className="flex items-center gap-3">
              <span
                className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-2xl"
                style={{ backgroundColor: `${ct.color}22` }}
                aria-hidden
              >
                {classTypeIcon(ct.key)}
              </span>
              <h2 className="font-heading text-xl font-bold">{ct.name}</h2>
            </div>
            <p className="mt-3 text-[var(--foreground)]">{ct.description}</p>

            {ct.pricingTiers.length > 0 && (
              <div className="mt-4 overflow-x-auto">
                <table className="w-full min-w-[280px] border-collapse text-sm">
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
              className="mt-4 inline-block rounded-full bg-[var(--sage)] px-4 py-2 text-sm font-semibold text-white shadow-sm transition-transform hover:scale-105 hover:opacity-90"
            >
              Zobacz szczegóły →
            </Link>
          </NotebookCard>
        ))}
      </div>

      <p className="mt-8 text-center text-sm text-[var(--muted)]">
        Zajęcia odbywają się 1x w tygodniu. Pełny terminarz i wolne miejsca znajdziesz w{" "}
        <Link href="/kalendarz" className="text-[var(--coral)] underline">
          grafiku
        </Link>
        . <span className="font-semibold text-[var(--foreground)]">Zniżki:</span> rodzeństwo −15%
        · Karta Dużej Rodziny −10%
      </p>
    </div>
  );
}
