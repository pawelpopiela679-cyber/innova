import Link from "next/link";
import { registerAction } from "@/lib/actions/auth-actions";

export default async function RegisterPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string; next?: string }>;
}) {
  const sp = await searchParams;

  return (
    <div className="mx-auto max-w-sm px-4 py-16">
      <h1 className="text-2xl font-extrabold">Załóż konto rodzica</h1>
      <p className="mt-1 text-sm text-[var(--muted)]">
        Konto pozwala dodać dzieci i zapisywać je na zajęcia.
      </p>

      {sp.error && (
        <p className="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{sp.error}</p>
      )}

      <form action={registerAction} className="mt-6 space-y-4">
        <input type="hidden" name="next" value={sp.next ?? ""} />
        <div>
          <label htmlFor="name" className="text-sm font-medium">
            Imię i nazwisko
          </label>
          <input
            id="name"
            name="name"
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>
        <div>
          <label htmlFor="email" className="text-sm font-medium">
            E-mail
          </label>
          <input
            id="email"
            name="email"
            type="email"
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>
        <div>
          <label htmlFor="phone" className="text-sm font-medium">
            Telefon (opcjonalnie)
          </label>
          <input
            id="phone"
            name="phone"
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>
        <div>
          <label htmlFor="password" className="text-sm font-medium">
            Hasło
          </label>
          <input
            id="password"
            name="password"
            type="password"
            required
            minLength={8}
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
          <p className="mt-1 text-xs text-[var(--muted)]">Co najmniej 8 znaków.</p>
        </div>
        <button
          type="submit"
          className="w-full rounded-full bg-[var(--primary)] py-2.5 font-semibold text-[var(--primary-foreground)] hover:opacity-90"
        >
          Załóż konto
        </button>
      </form>

      <p className="mt-6 text-sm text-[var(--muted)]">
        Masz już konto?{" "}
        <Link href="/logowanie" className="text-[var(--primary)] underline">
          Zaloguj się
        </Link>
      </p>
    </div>
  );
}
