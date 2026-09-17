import Link from "next/link";
import { prisma } from "@/lib/db";
import { classTypeIcon } from "@/lib/class-type-icons";
import {
  ArrowDoodle,
  HeartDoodle,
  LightbulbDoodle,
  StarDoodle,
} from "@/components/decor";
import { Logo } from "@/components/logo";
import { NotebookCard, StickyNote, SectionHeading } from "@/components/scrapbook";
import { NAV_LINKS } from "@/components/nav-links";

export default async function HomePage() {
  const classTypes = await prisma.classType.findMany({ orderBy: { createdAt: "asc" } });
  const pageLinks = NAV_LINKS.filter((l) => l.href !== "/");

  return (
    <div className="overflow-x-clip">
      {/* ---------------------------------------------------------------- Hero */}
      <section className="relative mx-auto max-w-6xl px-4 pb-16 pt-10 sm:pt-14">
        <StickyNote
          color="pink"
          className="absolute -top-2 right-2 hidden w-40 sm:block"
        >
          Tu zaczyna się przygoda! <HeartDoodle className="mx-auto mt-1 h-4 w-4" />
        </StickyNote>

        <div className="grid gap-10 lg:grid-cols-2 lg:items-center">
          <NotebookCard rotate="-rotate-1" className="mx-auto max-w-md text-center lg:mx-0">
            <Logo size="md" />
            <p className="mt-4 font-script text-3xl text-[var(--sage)]">
              Odkrywaj
              <br />
              Twórz
              <br />
              Rośnij
            </p>
            <HeartDoodle className="mx-auto mt-3 h-5 w-5" />
          </NotebookCard>

          <div className="relative text-center lg:text-left">
            <StarDoodle className="absolute -left-2 -top-6 hidden h-8 w-8 lg:block" />
            <h1 className="relative inline-block font-heading text-3xl font-extrabold leading-tight text-[var(--ink)] sm:text-5xl">
              Miejsce, w którym
              <br />
              <span className="relative">
                pomysły rosną!
                <span
                  className="absolute inset-x-0 bottom-1 -z-10 h-3 rounded-full opacity-60"
                  style={{ backgroundColor: "var(--pill-mint)" }}
                  aria-hidden
                />
              </span>
            </h1>
            <p className="mx-auto mt-5 max-w-xl text-lg text-[var(--muted)] lg:mx-0">
              Kreatywno-edukacyjne zajęcia dla dzieci i młodzieży, które inspirują,
              rozwijają pasje i dają nowe możliwości.
            </p>
            <div className="mt-8 flex flex-wrap justify-center gap-3 lg:justify-start">
              <Link
                href="/poznaj-nas"
                className="rounded-full bg-[var(--sage)] px-6 py-3 font-semibold text-[var(--primary-foreground)] shadow-md shadow-[var(--sage)]/20 transition-transform hover:scale-105 hover:opacity-90"
              >
                Poznaj nas bliżej →
              </Link>
              <Link
                href="/zajecia"
                className="rounded-full border border-[var(--border)] bg-[var(--surface)] px-6 py-3 font-semibold transition-colors hover:bg-[var(--background)]"
              >
                Zobacz zajęcia →
              </Link>
            </div>

            <div className="mx-auto mt-10 flex max-w-xl flex-wrap justify-center gap-3 lg:mx-0 lg:justify-start">
              <span className="flex items-center gap-2 rounded-full bg-[var(--coral-soft)] px-5 py-2.5 text-sm font-semibold text-[var(--coral)]">
                <span aria-hidden>⭐</span> Dzień otwarty: 12.09.2026
              </span>
              <span className="flex items-center gap-2 rounded-full bg-[var(--sage-soft)] px-5 py-2.5 text-sm font-semibold text-[var(--sage)]">
                <span aria-hidden>📅</span> Start zajęć: 14.09.2026
              </span>
            </div>
          </div>
        </div>
      </section>

      {/* -------------------------------------------------------- Page tabs grid */}
      <section className="mx-auto max-w-6xl px-4 pb-16">
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {pageLinks.map((link) => (
            <Link key={link.href} href={link.href} className="group block">
              <NotebookCard tab={{ label: link.label, color: link.color }}>
                <h3 className="font-heading text-lg font-bold group-hover:text-[var(--coral)]">
                  {link.label}
                </h3>
                <p className="mt-2 text-sm text-[var(--muted)]">
                  {PAGE_TEASERS[link.href]}
                </p>
                <span className="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-[var(--sage)]">
                  Przejdź <ArrowDoodle className="h-3 w-6" />
                </span>
              </NotebookCard>
            </Link>
          ))}
        </div>
      </section>

      {/* -------------------------------------------------------------- Oferta */}
      <section className="mx-auto max-w-6xl px-4 pb-16">
        <SectionHeading eyebrow="Nasza oferta" title="Zajęcia dla każdego" highlight="var(--pill-blue)" />
        <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {classTypes.map((ct) => (
            <Link
              key={ct.id}
              href={`/zajecia#${ct.key}`}
              className="group relative rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 pt-7 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-lg"
              style={{ borderTopWidth: 4, borderTopColor: ct.color }}
            >
              <div className="flex items-center gap-3">
                <span
                  className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xl"
                  style={{ backgroundColor: `${ct.color}22` }}
                  aria-hidden
                >
                  {classTypeIcon(ct.key)}
                </span>
                <h3 className="font-heading font-bold group-hover:text-[var(--coral)]">
                  {ct.name}
                </h3>
              </div>
              <p className="mt-3 text-sm text-[var(--muted)] line-clamp-3">{ct.description}</p>
              <p className="mt-3 text-xs font-semibold text-[var(--muted)]">
                Wiek: {ct.ageMin}–{ct.ageMax} lat
              </p>
            </Link>
          ))}
        </div>
        <p className="mt-4 text-center text-sm text-[var(--muted)]">
          Zajęcia odbywają się 1x w tygodniu.{" "}
          <Link href="/zajecia" className="text-[var(--coral)] underline">
            Zobacz pełny cennik →
          </Link>
        </p>
      </section>

      {/* --------------------------------------------------------- Dlaczego warto */}
      <section className="mx-auto max-w-6xl px-4 pb-16">
        <NotebookCard className="relative overflow-hidden">
          <LightbulbDoodle className="absolute -top-2 right-6 hidden h-12 w-12 opacity-70 sm:block" />
          <h2 className="text-center font-heading text-xl font-bold">Dlaczego warto?</h2>
          <div className="mt-6 grid gap-6 sm:grid-cols-3">
            <Reason icon="🧸" color="var(--sage)" title="Kameralne grupy" text="Mała liczba dzieci w grupie — więcej uwagi dla każdego." />
            <Reason icon="🤗" color="var(--coral)" title="Przyjazna atmosfera" text="Bezpieczna, ciepła przestrzeń, w której dzieci chętnie wracają." />
            <Reason icon="🎯" color="var(--mustard)" title="Nauka przez działanie" text="Wiedza i umiejętności zdobywane w praktyce, nie z podręcznika." />
          </div>
          <div className="mx-auto mt-6 flex max-w-md items-center justify-center gap-2 rounded-xl bg-[var(--mustard-soft)] px-5 py-3 text-center text-sm font-semibold text-[var(--foreground)]">
            <span aria-hidden>💛</span> Materiały podstawowe w cenie zajęć
          </div>
        </NotebookCard>
      </section>

      {/* -------------------------------------------------------------- Jak to działa */}
      <section className="mx-auto max-w-6xl px-4 pb-20">
        <NotebookCard rotate="rotate-0" className="text-center">
          <h2 className="font-heading text-xl font-bold">Jak to działa?</h2>
          <div className="mt-6 grid gap-6 text-left sm:grid-cols-2 lg:grid-cols-5">
            <Step n={1} title="Załóż konto rodzica" text="Szybka rejestracja e-mailem." />
            <Step
              n={2}
              title="Dodaj dziecko"
              text="Imię, nazwisko i data urodzenia — wiek pomaga dobrać grupę."
            />
            <Step
              n={3}
              title="Zgłoś chęć zapisu"
              text="Sprawdź kalendarz i wybierz termin, który Wam pasuje."
            />
            <Step
              n={4}
              title="Potwierdzamy grupę"
              text="Sprawdzamy dostępność i dobieramy grupę odpowiednią do wieku."
            />
            <Step
              n={5}
              title="Gotowe!"
              text="Dostajesz e-mail z potwierdzeniem i przypisaną grupą."
            />
          </div>
        </NotebookCard>
        <p className="mt-6 text-center text-sm text-[var(--muted)]">
          <span className="font-semibold text-[var(--foreground)]">Zniżki:</span> rodzeństwo −15%
          · Karta Dużej Rodziny −10%
        </p>
      </section>
    </div>
  );
}

const PAGE_TEASERS: Record<string, string> = {
  "/aktualnosci": "Wydarzenia, zajęcia i nowe możliwości — bądź na bieżąco z życiem INNOVA.",
  "/poznaj-nas": "Kim jesteśmy, jaka jest nasza misja i kto prowadzi zajęcia.",
  "/zajecia": "Angielski, zajęcia sceniczne, robotyka, kreatywne, matematyka, eksperymentatorium.",
  "/kalendarz": "Sprawdź plan zajęć — dni, godziny i wolne miejsca w grupach.",
  "/zapisy": "Jak się zapisać, formularz zgłoszeniowy i najczęstsze pytania.",
  "/kontakt": "Napisz do nas, zadzwoń albo odwiedź naszą pracownię.",
};

function Reason({
  icon,
  color,
  title,
  text,
}: {
  icon: string;
  color: string;
  title: string;
  text: string;
}) {
  return (
    <div className="text-center">
      <div
        className="mx-auto flex h-14 w-14 items-center justify-center rounded-full text-2xl"
        style={{ backgroundColor: `${color}22` }}
        aria-hidden
      >
        {icon}
      </div>
      <h3 className="mt-3 font-heading font-bold">{title}</h3>
      <p className="text-sm text-[var(--muted)]">{text}</p>
    </div>
  );
}

function Step({ n, title, text }: { n: number; title: string; text: string }) {
  return (
    <div>
      <div className="mb-2 flex h-8 w-8 items-center justify-center rounded-full bg-[var(--sage)] text-sm font-bold text-white">
        {n}
      </div>
      <h3 className="font-heading font-semibold">{title}</h3>
      <p className="text-sm text-[var(--muted)]">{text}</p>
    </div>
  );
}
