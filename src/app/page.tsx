import Link from "next/link";
import { prisma } from "@/lib/db";
import { classTypeIcon } from "@/lib/class-type-icons";
import { Blob, DashedDivider, HeartDoodle, HeroIllustration, Sparkle } from "@/components/decor";
import { Logo } from "@/components/logo";

export default async function HomePage() {
  const classTypes = await prisma.classType.findMany({ orderBy: { createdAt: "asc" } });

  return (
    <div className="overflow-x-clip">
      <section className="relative overflow-hidden">
        <Blob
          className="pointer-events-none absolute -left-24 -top-24 h-80 w-80 opacity-60 sm:h-96 sm:w-96"
          color="var(--sage-light)"
        />
        <Blob
          className="pointer-events-none absolute -right-28 top-10 h-72 w-72 opacity-40"
          color="var(--coral)"
        />

        <div className="relative mx-auto grid max-w-6xl gap-10 px-4 py-16 lg:grid-cols-2 lg:items-center lg:py-24">
          <div className="text-center lg:text-left">
            <div className="flex justify-center lg:justify-start">
              <Logo size="lg" withSubtitle />
            </div>

            <div className="mt-4 flex items-center justify-center gap-2 lg:justify-start">
              <p className="font-script text-4xl text-[var(--sage)] sm:text-5xl">Miejsce rozwoju</p>
              <HeartDoodle className="mb-2 h-6 w-6 self-end" />
            </div>
            <h1 className="mt-1 font-heading text-2xl font-bold tracking-tight text-[var(--coral)] sm:text-3xl">
              DLA TWOJEGO DZIECKA
            </h1>
            <DashedDivider className="mx-auto mt-5 w-40 lg:mx-0" />

            <p className="mx-auto mt-5 max-w-xl text-lg text-[var(--muted)] lg:mx-0">
              Zapisz dziecko na zajęcia w kilka minut: sprawdź kalendarz, wybierz
              termin i od razu otrzymaj potwierdzenie zapisu na e-mail.
            </p>
            <div className="mt-8 flex flex-wrap justify-center gap-3 lg:justify-start">
              <Link
                href="/kalendarz"
                className="rounded-full bg-[var(--sage)] px-6 py-3 font-semibold text-[var(--primary-foreground)] shadow-md shadow-[var(--sage)]/20 transition-transform hover:scale-105 hover:opacity-90"
              >
                Zobacz kalendarz zajęć
              </Link>
              <Link
                href="/rejestracja"
                className="rounded-full border border-[var(--border)] bg-[var(--surface)] px-6 py-3 font-semibold transition-colors hover:bg-[var(--background)]"
              >
                Załóż konto rodzica
              </Link>
            </div>

            <div className="mx-auto mt-10 flex max-w-xl flex-wrap justify-center gap-3 lg:mx-0 lg:justify-start">
              <span className="flex items-center gap-2 rounded-full bg-[var(--sage-soft)] px-5 py-2.5 text-sm font-semibold text-[var(--sage)]">
                <span aria-hidden>📅</span> Start zajęć: 7.09.2026
              </span>
              <span className="flex items-center gap-2 rounded-full bg-[var(--coral-soft)] px-5 py-2.5 text-sm font-semibold text-[var(--coral)]">
                <span aria-hidden>⭐</span> Bezpłatne zajęcia pokazowe: 5.09.2026
              </span>
            </div>
          </div>

          <div className="relative mx-auto w-full max-w-md lg:max-w-none">
            <Sparkle className="absolute -left-2 top-2 h-7 w-7" color="var(--mustard)" />
            <Sparkle className="absolute right-6 top-0 h-5 w-5" color="var(--coral)" />
            <HeroIllustration className="w-full drop-shadow-sm" />
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 pb-16">
        <h2 className="mb-6 text-center font-heading text-2xl font-bold">Nasza oferta</h2>
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {classTypes.map((ct, i) => (
            <Link
              key={ct.id}
              href={`/zajecia#${ct.key}`}
              className="group relative rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 pt-7 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-lg"
              style={{ borderTopWidth: 4, borderTopColor: ct.color }}
            >
              <span
                className="absolute -top-4 left-5 flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold text-white shadow-sm"
                style={{ backgroundColor: ct.color }}
                aria-hidden
              >
                {i + 1}
              </span>
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

      <section className="mx-auto max-w-6xl px-4 pb-16">
        <div className="relative overflow-hidden rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-8">
          <Blob
            className="pointer-events-none absolute -bottom-16 -right-16 h-48 w-48 opacity-20"
            color="var(--mustard)"
          />
          <h2 className="relative text-center font-heading text-xl font-bold">Dlaczego warto?</h2>
          <div className="relative mt-6 grid gap-6 sm:grid-cols-3">
            <Reason icon="🧸" color="var(--sage)" title="Kameralne grupy" text="Mała liczba dzieci w grupie — więcej uwagi dla każdego." />
            <Reason icon="🤗" color="var(--coral)" title="Przyjazna atmosfera" text="Bezpieczna, ciepła przestrzeń, w której dzieci chętnie wracają." />
            <Reason icon="🎯" color="var(--mustard)" title="Nauka przez działanie" text="Wiedza i umiejętności zdobywane w praktyce, nie z podręcznika." />
          </div>
          <div className="relative mx-auto mt-6 flex max-w-md items-center justify-center gap-2 rounded-xl bg-[var(--mustard-soft)] px-5 py-3 text-center text-sm font-semibold text-[var(--foreground)]">
            <span aria-hidden>💛</span> Materiały podstawowe w cenie zajęć
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 pb-16">
        <div className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-8 text-center">
          <h2 className="font-heading text-xl font-bold">Jak to działa?</h2>
          <div className="mt-6 grid gap-6 text-left sm:grid-cols-4">
            <Step n={1} title="Załóż konto rodzica" text="Szybka rejestracja e-mailem." />
            <Step
              n={2}
              title="Dodaj dziecko"
              text="Imię, nazwisko i data urodzenia wystarczą."
            />
            <Step
              n={3}
              title="Wybierz termin"
              text="Sprawdź kalendarz i opisy zajęć na dany dzień."
            />
            <Step
              n={4}
              title="Gotowe!"
              text="Zapis jest od razu potwierdzony e-mailem, a my dostajemy powiadomienie."
            />
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-3xl px-4 pb-20 text-center text-sm text-[var(--muted)]">
        <p>
          <span className="font-semibold text-[var(--foreground)]">Zniżki:</span> rodzeństwo −15%
          · Karta Dużej Rodziny −10%
        </p>
      </section>
    </div>
  );
}

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
