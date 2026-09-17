import { NotebookCard, SectionHeading } from "@/components/scrapbook";
import { submitContactAction } from "@/lib/actions/contact-actions";

export default async function ContactPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string; success?: string }>;
}) {
  const sp = await searchParams;

  return (
    <div className="mx-auto max-w-6xl px-4 py-12">
      <SectionHeading
        eyebrow="Dobry pomysł zaczyna się od rozmowy!"
        title="Kontakt"
        subtitle="Masz pytania? Napisz do nas!"
        highlight="var(--pill-teal)"
      />

      <div className="mt-10 grid gap-6 lg:grid-cols-3 lg:items-start">
        <NotebookCard rotate="-rotate-1">
          <h2 className="font-heading text-lg font-bold">Napisz do nas</h2>
          {sp.success ? (
            <div className="py-8 text-center">
              <p className="text-3xl">💌</p>
              <p className="mt-2 font-semibold text-[var(--sage)]">Wiadomość wysłana!</p>
              <p className="mt-1 text-sm text-[var(--muted)]">
                Odpowiemy najszybciej, jak to możliwe.
              </p>
            </div>
          ) : (
            <>
              {sp.error && (
                <p className="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{sp.error}</p>
              )}
              <form action={submitContactAction} className="mt-4 space-y-4">
                <div>
                  <label htmlFor="name" className="text-sm font-medium">
                    Imię i nazwisko *
                  </label>
                  <input
                    id="name"
                    name="name"
                    required
                    className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
                  />
                </div>
                <div>
                  <label htmlFor="email" className="text-sm font-medium">
                    Adres e-mail *
                  </label>
                  <input
                    id="email"
                    name="email"
                    type="email"
                    required
                    className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
                  />
                </div>
                <div>
                  <label htmlFor="subject" className="text-sm font-medium">
                    Temat
                  </label>
                  <input
                    id="subject"
                    name="subject"
                    className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
                  />
                </div>
                <div>
                  <label htmlFor="message" className="text-sm font-medium">
                    Wiadomość *
                  </label>
                  <textarea
                    id="message"
                    name="message"
                    required
                    rows={4}
                    className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
                  />
                </div>
                <label className="flex items-start gap-2 text-sm text-[var(--muted)]">
                  <input type="checkbox" name="consent" required className="mt-1" />
                  Wyrażam zgodę na przetwarzanie moich danych osobowych w celu odpowiedzi na
                  wiadomość.
                </label>
                <button
                  type="submit"
                  className="w-full rounded-full bg-[var(--sage)] py-2.5 font-semibold text-white shadow-sm transition-transform hover:scale-[1.02] hover:opacity-90"
                >
                  Wyślij wiadomość ✈
                </button>
              </form>
            </>
          )}
        </NotebookCard>

        <NotebookCard rotate="rotate-1">
          <h2 className="font-heading text-lg font-bold">Nasze dane</h2>
          <ul className="mt-4 space-y-3 text-sm">
            <li className="flex items-start gap-2">
              <span aria-hidden>📍</span>
              <span>
                ul. Kolejowa
                <br />
                Czechowice-Dziedzice
              </span>
            </li>
            <li className="flex items-center gap-2">
              <span aria-hidden>✉️</span>
              <a href="mailto:kontakt@innova-pracownia.pl" className="hover:text-[var(--coral)]">
                kontakt@innova-pracownia.pl
              </a>
            </li>
            <li className="flex items-center gap-2">
              <span aria-hidden>📞</span>
              <a href="tel:+48570250363" className="hover:text-[var(--coral)]">
                570 250 363
              </a>
            </li>
          </ul>
          <div className="mt-6 border-t border-[var(--border)] pt-4">
            <h3 className="text-center text-sm font-semibold">Znajdź nas w social mediach</h3>
            <div className="mt-3 flex justify-center gap-3">
              <a
                href="https://facebook.com/innova.pracownia"
                className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--pill-blue)] text-lg"
                aria-label="Facebook"
              >
                📘
              </a>
              <a
                href="https://instagram.com/innova_pracownia"
                className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--pill-pink)] text-lg"
                aria-label="Instagram"
              >
                📷
              </a>
            </div>
          </div>
        </NotebookCard>

        <NotebookCard rotate="-rotate-1">
          <h2 className="font-heading text-lg font-bold">Tu nas znajdziesz</h2>
          <div className="mt-4 overflow-hidden rounded-xl border border-[var(--border)]">
            <iframe
              title="Mapa — Czechowice-Dziedzice, ul. Kolejowa"
              src="https://www.openstreetmap.org/export/embed.html?bbox=18.98%2C49.90%2C19.04%2C49.94&layer=mapnik&marker=49.92%2C19.01"
              className="h-64 w-full"
              loading="lazy"
            />
          </div>
          <p className="mt-3 text-center text-sm text-[var(--muted)]">
            Łatwy dojazd! Czekamy na Ciebie w Czechowicach-Dziedzicach.
          </p>
        </NotebookCard>
      </div>
    </div>
  );
}
