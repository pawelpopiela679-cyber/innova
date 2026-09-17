import Link from "next/link";
import { NotebookCard, SectionHeading, StickyNote } from "@/components/scrapbook";
import { CheckDoodle } from "@/components/decor";
import { submitLeadAction } from "@/lib/actions/lead-actions";

const STEPS = [
  {
    n: 1,
    title: "Wypełnij formularz",
    text: "Podaj podstawowe informacje o dziecku i swoje dane kontaktowe.",
  },
  {
    n: 2,
    title: "Otrzymaj potwierdzenie",
    text: "Skontaktujemy się z Tobą, aby potwierdzić zapis i odpowiedzieć na pytania.",
  },
  {
    n: 3,
    title: "Dołącz do zajęć!",
    text: "Widzimy się na kreatywnych zajęciach — razem odkrywamy talenty i nowe możliwości!",
  },
];

const FAQ = [
  { q: "Od jakiego wieku można się zapisać?", a: "Przyjmujemy dzieci i młodzież w wieku od 3 do 12+ lat — zajęcia są dobrane do konkretnych grup wiekowych." },
  { q: "Ile trwają zajęcia?", a: "Standardowo 50–60 minut, raz w tygodniu — dokładny czas trwania znajdziesz przy każdych zajęciach na stronie „Zajęcia”." },
  { q: "Czy mogę zapisać dziecko na zajęcia próbne?", a: "Tak — napisz do nas przez formularz lub stronę „Kontakt”, a zaproponujemy termin zajęć pokazowych." },
  { q: "Jak wygląda płatność?", a: "Płatność miesięczna, ustalana indywidualnie po potwierdzeniu zapisu — szczegóły przekażemy mailowo." },
];

export default async function SignupsPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string; success?: string }>;
}) {
  const sp = await searchParams;

  return (
    <div className="mx-auto max-w-5xl px-4 py-12">
      <SectionHeading
        eyebrow="Kreatywne dzieci to szczęśliwe dzieci!"
        title="Zapisy"
        subtitle="Dołącz do naszej kreatywnej przygody"
        highlight="var(--pill-orange)"
      />

      <div className="mt-10 grid gap-8 lg:grid-cols-2 lg:items-start">
        <NotebookCard rotate="-rotate-1">
          <h2 className="font-heading text-xl font-bold text-[var(--sage)]">Jak się zapisać?</h2>
          <div className="mt-5 space-y-5">
            {STEPS.map((s) => (
              <div key={s.n} className="flex gap-3">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 border-[var(--sage)] text-sm font-bold text-[var(--sage)]">
                  {s.n}
                </span>
                <div>
                  <h3 className="font-heading font-semibold">{s.title}</h3>
                  <p className="text-sm text-[var(--muted)]">{s.text}</p>
                </div>
              </div>
            ))}
          </div>
          <p className="mt-6 text-sm text-[var(--muted)]">
            Masz już konto rodzica?{" "}
            <Link href="/kalendarz" className="font-semibold text-[var(--coral)] underline">
              Przejdź do kalendarza i zapisz dziecko na konkretny termin →
            </Link>
          </p>
        </NotebookCard>

        <NotebookCard rotate="rotate-1" tab={{ label: "Zapisz dziecko", color: "var(--pill-pink)" }}>
          {sp.success ? (
            <div className="py-6 text-center">
              <p className="text-3xl">🎉</p>
              <h2 className="mt-2 font-heading text-lg font-bold text-[var(--sage)]">
                Dziękujemy za zgłoszenie!
              </h2>
              <p className="mt-2 text-sm text-[var(--muted)]">
                Skontaktujemy się wkrótce, aby potwierdzić zapis i dobrać odpowiednią grupę.
              </p>
            </div>
          ) : (
            <>
              {sp.error && (
                <p className="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{sp.error}</p>
              )}
              <form action={submitLeadAction} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                  <div>
                    <label htmlFor="childName" className="text-sm font-medium">
                      Imię dziecka *
                    </label>
                    <input
                      id="childName"
                      name="childName"
                      required
                      placeholder="np. Zosia"
                      className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
                    />
                  </div>
                  <div>
                    <label htmlFor="childAge" className="text-sm font-medium">
                      Wiek dziecka *
                    </label>
                    <input
                      id="childAge"
                      name="childAge"
                      type="number"
                      min={1}
                      max={18}
                      required
                      placeholder="np. 7"
                      className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
                    />
                  </div>
                </div>
                <div>
                  <label htmlFor="parentEmail" className="text-sm font-medium">
                    Twój adres e-mail *
                  </label>
                  <input
                    id="parentEmail"
                    name="parentEmail"
                    type="email"
                    required
                    placeholder="np. anna@przyklad.pl"
                    className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
                  />
                </div>
                <div>
                  <label htmlFor="parentPhone" className="text-sm font-medium">
                    Numer telefonu *
                  </label>
                  <input
                    id="parentPhone"
                    name="parentPhone"
                    required
                    placeholder="np. 123 456 789"
                    className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
                  />
                </div>
                <label className="flex items-start gap-2 text-sm text-[var(--muted)]">
                  <input type="checkbox" name="consent" required className="mt-1" />
                  Wyrażam zgodę na kontakt w sprawie zapisów i organizacji zajęć.
                </label>
                <button
                  type="submit"
                  className="w-full rounded-full bg-[var(--coral)] py-2.5 font-semibold text-white shadow-sm transition-transform hover:scale-[1.02] hover:opacity-90"
                >
                  Wyślij zgłoszenie →
                </button>
              </form>
            </>
          )}
        </NotebookCard>
      </div>

      <div className="mt-12">
        <h2 className="text-center font-heading text-xl font-bold">Najczęściej zadawane pytania</h2>
        <div className="mx-auto mt-6 grid max-w-3xl gap-3">
          {FAQ.map((item) => (
            <details
              key={item.q}
              className="group rounded-xl border border-[var(--border)] bg-[var(--surface)] p-4 open:shadow-sm"
            >
              <summary className="flex cursor-pointer list-none items-center gap-2 font-semibold marker:content-none">
                <CheckDoodle className="h-4 w-4 shrink-0" />
                {item.q}
              </summary>
              <p className="mt-2 pl-6 text-sm text-[var(--muted)]">{item.a}</p>
            </details>
          ))}
        </div>
      </div>

      <div className="mt-10 flex flex-col items-center gap-4">
        <p className="text-center text-[var(--muted)]">Masz inne pytania? Skontaktuj się z nami!</p>
        <Link
          href="/kontakt"
          className="rounded-full bg-[var(--sage)] px-6 py-2.5 font-semibold text-white shadow-sm transition-transform hover:scale-105 hover:opacity-90"
        >
          Przejdź do kontaktu
        </Link>
        <StickyNote color="purple" className="max-w-xs">
          Kreatywność zmienia świat! 💜
        </StickyNote>
      </div>
    </div>
  );
}
