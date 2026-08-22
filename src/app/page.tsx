import Link from "next/link";
import { prisma } from "@/lib/db";
import { classTypeIcon } from "@/lib/class-type-icons";

export default async function HomePage() {
  const classTypes = await prisma.classType.findMany({ orderBy: { createdAt: "asc" } });

  return (
    <div>
      <section className="mx-auto max-w-6xl px-4 py-16 text-center">
        <p className="font-heading text-2xl font-bold tracking-tight sm:text-3xl">
          <span className="text-[var(--sage)]">IN</span>
          <span className="text-[var(--coral)]">NOVA</span>
          <span className="ml-2 align-middle text-base font-normal text-[var(--muted)] sm:text-lg">
            — Pracownia kreatywno-edukacyjna
          </span>
        </p>
        <p className="font-script mt-4 text-4xl text-[var(--sage)] sm:text-5xl">Miejsce rozwoju</p>
        <h1 className="mt-1 font-heading text-2xl font-bold tracking-tight text-[var(--coral)] sm:text-3xl">
          DLA TWOJEGO DZIECKA
        </h1>
        <p className="mx-auto mt-5 max-w-2xl text-lg text-[var(--muted)]">
          Zapisz dziecko na zajęcia w kilka minut: sprawdź kalendarz, wybierz
          termin i od razu otrzymaj potwierdzenie zapisu na e-mail.
        </p>
        <div className="mt-8 flex flex-wrap justify-center gap-3">
          <Link
            href="/kalendarz"
            className="rounded-full bg-[var(--sage)] px-6 py-3 font-semibold text-[var(--primary-foreground)] hover:opacity-90"
          >
            Zobacz kalendarz zajęć
          </Link>
          <Link
            href="/rejestracja"
            className="rounded-full border border-[var(--border)] bg-[var(--surface)] px-6 py-3 font-semibold hover:bg-[var(--background)]"
          >
            Załóż konto rodzica
          </Link>
        </div>

        <div className="mx-auto mt-10 flex max-w-xl flex-wrap justify-center gap-3">
          <span className="flex items-center gap-2 rounded-full bg-[var(--sage)] px-5 py-2.5 text-sm font-semibold text-white">
            <span aria-hidden>📅</span> Start zajęć: 7.09.2026
          </span>
          <span className="flex items-center gap-2 rounded-full bg-[var(--coral)] px-5 py-2.5 text-sm font-semibold text-white">
            <span aria-hidden>⭐</span> Bezpłatne zajęcia pokazowe: 5.09.2026
          </span>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 pb-16">
        <h2 className="mb-6 text-center font-heading text-2xl font-bold">Nasza oferta</h2>
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {classTypes.map((ct) => (
            <Link
              key={ct.id}
              href={`/zajecia#${ct.key}`}
              className="group rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 shadow-sm transition hover:shadow-md"
              style={{ borderTopWidth: 4, borderTopColor: ct.color }}
            >
              <div className="flex items-center gap-2">
                <span className="text-2xl" aria-hidden>
                  {classTypeIcon(ct.key)}
                </span>
                <h3 className="font-heading font-bold group-hover:text-[var(--coral)]">
                  {ct.name}
                </h3>
              </div>
              <p className="mt-2 text-sm text-[var(--muted)] line-clamp-3">{ct.description}</p>
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
        <div className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-8">
          <h2 className="text-center font-heading text-xl font-bold">Dlaczego warto?</h2>
          <div className="mt-6 grid gap-6 sm:grid-cols-3">
            <Reason icon="🧸" title="Kameralne grupy" text="Mała liczba dzieci w grupie — więcej uwagi dla każdego." />
            <Reason icon="🤗" title="Przyjazna atmosfera" text="Bezpieczna, ciepła przestrzeń, w której dzieci chętnie wracają." />
            <Reason icon="🎯" title="Nauka przez działanie" text="Wiedza i umiejętności zdobywane w praktyce, nie z podręcznika." />
          </div>
          <div className="mx-auto mt-6 flex max-w-md items-center justify-center gap-2 rounded-xl bg-[var(--mustard)] px-5 py-3 text-center text-sm font-semibold text-white">
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

function Reason({ icon, title, text }: { icon: string; title: string; text: string }) {
  return (
    <div className="text-center">
      <div className="text-3xl" aria-hidden>
        {icon}
      </div>
      <h3 className="mt-2 font-heading font-bold">{title}</h3>
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
