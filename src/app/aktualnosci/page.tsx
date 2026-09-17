import Link from "next/link";
import { NotebookCard, SectionHeading, StickyNote } from "@/components/scrapbook";

const NEWS = [
  {
    date: "12 kwietnia 2025",
    icon: "💡",
    title: "Dzień Otwarty w INNOVA!",
    excerpt:
      "Już 20 kwietnia zapraszamy na Dzień Otwarty! To doskonała okazja, aby poznać naszą pracownię, porozmawiać z prowadzącymi, zobaczyć przestrzeń i wziąć udział w bezpłatnych zajęciach pokazowych.",
    featured: true,
    cta: { href: "/zapisy", label: "Zapisz dziecko" },
  },
  {
    date: "18 maja 2025",
    icon: "❤",
    title: "Rodzinny piknik w INNOVA",
    excerpt:
      "Dużo zabawy, kreatywne strefy, animacje i wspólne tworzenie! Dziękujemy wszystkim, którzy byli z nami — to był wspaniały dzień.",
  },
  {
    date: "5 czerwca 2025",
    icon: "☀",
    title: "Letnie warsztaty twórcze",
    excerpt:
      "Kreatywne lato w INNOVA! Zapraszamy dzieci i młodzież na wyjątkowe warsztaty artystyczne. Będzie twórczo, radośnie i inspirująco.",
  },
  {
    date: "1 września 2025",
    icon: "⭐",
    title: "Nowe zajęcia od września",
    excerpt:
      "Od września w naszej pracowni ruszają nowe zajęcia! Rozszerzamy ofertę o robotykę, eksperymenty i twórcze pisanie. Sprawdź szczegóły!",
    cta: { href: "/zajecia", label: "Zobacz ofertę" },
  },
];

export default function NewsPage() {
  const [featured, ...rest] = NEWS;

  return (
    <div className="mx-auto max-w-6xl px-4 py-12">
      <div className="relative">
        <StickyNote color="pink" className="absolute -top-4 right-2 hidden w-44 sm:block">
          Trwają zapisy na nowy semestr! 🎉
        </StickyNote>
        <SectionHeading
          eyebrow="Bądź na bieżąco z życiem INNOVA!"
          title="Aktualności"
          highlight="var(--pill-pink)"
        />
      </div>

      <NotebookCard rotate="-rotate-1" className="mt-10">
        <p className="text-sm text-[var(--muted)]">{featured.date}</p>
        <h2 className="mt-1 font-heading text-2xl font-bold">
          {featured.icon} {featured.title}
        </h2>
        <p className="mt-3 max-w-2xl text-[var(--foreground)]">{featured.excerpt}</p>
        {featured.cta && (
          <Link
            href={featured.cta.href}
            className="mt-4 inline-block rounded-full bg-[var(--sage)] px-4 py-2 text-sm font-semibold text-white shadow-sm transition-transform hover:scale-105 hover:opacity-90"
          >
            {featured.cta.label} →
          </Link>
        )}
      </NotebookCard>

      <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {rest.map((item, i) => (
          <NotebookCard key={item.title} rotate={i % 2 === 0 ? "rotate-1" : "-rotate-1"}>
            <p className="text-sm text-[var(--muted)]">{item.date}</p>
            <h3 className="mt-1 font-heading text-lg font-bold">
              {item.icon} {item.title}
            </h3>
            <details className="group mt-2">
              <summary className="cursor-pointer list-none text-sm text-[var(--foreground)] marker:content-none">
                {item.excerpt.slice(0, 70)}…{" "}
                <span className="font-semibold text-[var(--coral)] underline">Czytaj więcej</span>
              </summary>
              <p className="mt-2 text-sm text-[var(--foreground)]">{item.excerpt}</p>
              {item.cta && (
                <Link href={item.cta.href} className="mt-2 inline-block text-sm font-semibold text-[var(--coral)] underline">
                  {item.cta.label} →
                </Link>
              )}
            </details>
          </NotebookCard>
        ))}
      </div>
    </div>
  );
}
