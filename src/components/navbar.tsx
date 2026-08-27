import Link from "next/link";
import { getSession } from "@/lib/auth";
import { logoutAction } from "@/lib/actions/auth-actions";
import { Logo } from "@/components/logo";

export async function Navbar() {
  const session = await getSession();
  const isStaff = session?.role === "ADMIN" || session?.role === "INSTRUCTOR";

  return (
    <header className="sticky top-0 z-30 border-b border-[var(--border)] bg-[var(--surface)]/85 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <Link href="/" className="transition-transform hover:scale-105">
          <Logo size="sm" />
        </Link>

        <nav className="hidden md:flex items-center gap-5 text-sm font-semibold">
          <Link href="/zajecia" className="hover:text-[var(--coral)]">
            Zajęcia i cennik
          </Link>
          <Link href="/kalendarz" className="hover:text-[var(--coral)]">
            Kalendarz
          </Link>
          {isStaff && (
            <Link href="/admin" className="hover:text-[var(--coral)]">
              Panel prowadzącego
            </Link>
          )}
        </nav>

        <div className="flex items-center gap-3 text-sm">
          {session ? (
            <>
              {!isStaff && (
                <Link
                  href="/panel"
                  className="hidden sm:inline hover:text-[var(--primary)]"
                >
                  Panel rodzica
                </Link>
              )}
              <span className="hidden sm:inline text-[var(--muted)]">
                {session.name}
              </span>
              <form action={logoutAction}>
                <button
                  type="submit"
                  className="rounded-full border border-[var(--border)] px-3 py-1.5 transition-colors hover:bg-[var(--background)]"
                >
                  Wyloguj
                </button>
              </form>
            </>
          ) : (
            <>
              <Link
                href="/logowanie"
                className="rounded-full border border-[var(--border)] px-3 py-1.5 transition-colors hover:bg-[var(--background)]"
              >
                Zaloguj się
              </Link>
              <Link
                href="/rejestracja"
                className="rounded-full bg-[var(--primary)] px-3 py-1.5 text-[var(--primary-foreground)] shadow-sm transition-transform hover:scale-105 hover:opacity-90"
              >
                Załóż konto
              </Link>
            </>
          )}
        </div>
      </div>
    </header>
  );
}
