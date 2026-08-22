import Link from "next/link";
import { prisma } from "@/lib/db";

export default async function HomePage() {
  const classTypes = await prisma.classType.findMany({ orderBy: { createdAt: "asc" } });

  return (
    <div>
      <section className="mx-auto max-w-6xl px-4 py-16 text-center">
        <h1 className="text-4xl md:text-5xl font-extrabold tracking-tight">
          Kreatywna pracownia dla dzieci — teraz również{" "}
          <span className="text-[var(--primary)]">online</span>
        </h1>
        <p className="mx-auto mt-4 max-w-2xl text-lg text-[var(--muted)]">
          Zapisz dziecko na zajęcia w kilka minut: sprawdź kalendarz, wybierz
          termin i od razu otrzymaj potwierdzenie zapisu na e-mail.
        </p>
        <div className="mt-8 flex justify-center gap-3">
          <Link
            href="/kalendarz"
            className="rounded-full bg-[var(--primary)] px-6 py-3 font-semibold text-[var(--primary-foreground)] hover:opacity-90"
          >
            Zobacz kalendarz zajęć
          </Link>
          <Link
            href="/rejestracja"
            className="rounded-full border border-[var(--border)] px-6 py-3 font-semibold hover:bg-[var(--surface)]"
          >
            Załóż konto rodzica
          </Link>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 pb-16">
        <h2 className="mb-6 text-center text-2xl font-bold">Nasze zajęcia</h2>
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {classTypes.map((ct) => (
            <Link
              key={ct.id}
              href={`/zajecia#${ct.key}`}
              className="group rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 shadow-sm transition hover:shadow-md"
            >
              <div
                className="mb-3 h-2 w-10 rounded-full"
                style={{ backgroundColor: ct.color }}
              />
              <h3 className="font-bold group-hover:text-[var(--primary)]">{ct.name}</h3>
              <p className="mt-2 text-sm text-[var(--muted)] line-clamp-4">
                {ct.description}
              </p>
              <p className="mt-3 text-xs text-[var(--muted)]">
                Wiek: {ct.ageMin}–{ct.ageMax} lat
              </p>
            </Link>
          ))}
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 pb-20">
        <div className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-8 text-center">
          <h2 className="text-xl font-bold">Jak to działa?</h2>
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
    </div>
  );
}

function Step({ n, title, text }: { n: number; title: string; text: string }) {
  return (
    <div>
      <div className="mb-2 flex h-8 w-8 items-center justify-center rounded-full bg-[var(--primary)] text-sm font-bold text-[var(--primary-foreground)]">
        {n}
      </div>
      <h3 className="font-semibold">{title}</h3>
      <p className="text-sm text-[var(--muted)]">{text}</p>
    </div>
  );
}
