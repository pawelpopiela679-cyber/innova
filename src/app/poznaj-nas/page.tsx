import { prisma } from "@/lib/db";
import { NotebookCard, SectionHeading, StickyNote } from "@/components/scrapbook";
import { CheckDoodle, HeartDoodle } from "@/components/decor";

export default async function AboutUsPage() {
  const instructors = await prisma.user.findMany({
    where: { role: "INSTRUCTOR" },
    orderBy: { name: "asc" },
  });

  return (
    <div className="mx-auto max-w-6xl px-4 py-12">
      <SectionHeading
        eyebrow="Razem tworzymy lepsze jutro!"
        title="O nas"
        subtitle="Tworzymy przestrzeń, w której dzieci i młodzież odkrywają swoje talenty."
        highlight="var(--pill-mint)"
      />

      <div className="mt-10 grid gap-5 sm:grid-cols-3">
        <NotebookCard rotate="-rotate-1">
          <h2 className="font-heading text-lg font-bold text-[var(--sage)]">🎯 Nasza misja</h2>
          <p className="mt-2 text-sm text-[var(--muted)]">
            Inspirujemy, wspieramy i dajemy narzędzia dzieciom i młodzieży, aby mogły
            odkrywać swoje pasje, rozwijać umiejętności i z odwagą sięgać po więcej.
          </p>
        </NotebookCard>

        <NotebookCard rotate="rotate-1">
          <h2 className="font-heading text-lg font-bold text-[var(--mustard)]">💎 Nasze wartości</h2>
          <ul className="mt-2 space-y-1.5 text-sm text-[var(--muted)]">
            {[
              "Szacunek do każdego dziecka",
              "Kreatywność w działaniu",
              "Współpraca i otwartość",
              "Rozwój przez doświadczenie",
              "Przyjazna atmosfera",
            ].map((v) => (
              <li key={v} className="flex items-start gap-2">
                <CheckDoodle className="mt-0.5 h-4 w-4 shrink-0" />
                {v}
              </li>
            ))}
          </ul>
        </NotebookCard>

        <NotebookCard rotate="-rotate-1">
          <h2 className="font-heading text-lg font-bold text-[var(--coral)]">🏠 Nasza przestrzeń</h2>
          <p className="mt-2 text-sm text-[var(--muted)]">
            INNOVA to przytulne, twórcze miejsce, w którym dzieci i młodzież mogą czuć
            się swobodnie, rozwijać swoje pomysły i spędzać czas w inspirującym otoczeniu.
          </p>
        </NotebookCard>
      </div>

      <div className="mt-12 flex items-center justify-center gap-2">
        <h2 className="text-center font-heading text-2xl font-bold">Poznaj nasz zespół</h2>
        <HeartDoodle className="h-5 w-5" />
      </div>
      <p className="mx-auto mt-2 max-w-xl text-center text-[var(--muted)]">
        Za INNOVA stoją ludzie z pasją — pedagodzy, animatorzy, artyści i pasjonaci
        edukacji. Łączy nas wiara w potencjał młodych ludzi i chęć tworzenia miejsca, w
        którym każdy może rozwinąć skrzydła.
      </p>

      {instructors.length === 0 ? (
        <StickyNote color="yellow" className="mx-auto mt-10 max-w-xs">
          Wkrótce przedstawimy tu nasz zespół.
        </StickyNote>
      ) : (
        <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {instructors.map((i, idx) => (
            <NotebookCard key={i.id} rotate={idx % 2 === 0 ? "-rotate-1" : "rotate-1"}>
              <div className="flex flex-col items-center text-center">
                {i.avatarUrl ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={i.avatarUrl}
                    alt={i.name}
                    className="h-24 w-24 rounded-xl border-4 border-white object-cover shadow-sm"
                  />
                ) : (
                  <div className="flex h-24 w-24 items-center justify-center rounded-xl border-4 border-white bg-[var(--sage-soft)] text-3xl font-bold text-[var(--sage)] shadow-sm">
                    {i.name.charAt(0)}
                  </div>
                )}
                <h3 className="mt-3 font-heading font-bold">{i.name}</h3>
                {i.bio && <p className="mt-1 text-sm text-[var(--muted)]">{i.bio}</p>}
              </div>
            </NotebookCard>
          ))}
        </div>
      )}
    </div>
  );
}
