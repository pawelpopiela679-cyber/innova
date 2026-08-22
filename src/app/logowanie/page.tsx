import Link from "next/link";
import { loginAction } from "@/lib/actions/auth-actions";

export default async function LoginPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string; next?: string }>;
}) {
  const sp = await searchParams;

  return (
    <div className="mx-auto max-w-sm px-4 py-16">
      <h1 className="text-2xl font-extrabold">Zaloguj się</h1>
      <p className="mt-1 text-sm text-[var(--muted)]">
        Zaloguj się, aby zapisać dziecko na zajęcia lub sprawdzić swoje zapisy.
      </p>

      {sp.error && (
        <p className="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{sp.error}</p>
      )}

      <form action={loginAction} className="mt-6 space-y-4">
        <input type="hidden" name="next" value={sp.next ?? ""} />
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
          <label htmlFor="password" className="text-sm font-medium">
            Hasło
          </label>
          <input
            id="password"
            name="password"
            type="password"
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>
        <button
          type="submit"
          className="w-full rounded-full bg-[var(--primary)] py-2.5 font-semibold text-[var(--primary-foreground)] hover:opacity-90"
        >
          Zaloguj się
        </button>
      </form>

      <p className="mt-6 text-sm text-[var(--muted)]">
        Nie masz jeszcze konta?{" "}
        <Link href="/rejestracja" className="text-[var(--primary)] underline">
          Zarejestruj się
        </Link>
      </p>
    </div>
  );
}
