import Link from "next/link";
import { getSession } from "@/lib/auth";
import { logoutAction } from "@/lib/actions/auth-actions";
import { Logo } from "@/components/logo";
import { HeartDoodle } from "@/components/decor";
import { NavPills } from "@/components/nav-pills";
import { MobileMenu } from "@/components/mobile-menu";
import { NAV_LINKS } from "@/components/nav-links";
import { prisma } from "@/lib/db";

const CUSTOM_PAGE_COLORS = [
  "var(--pill-green)",
  "var(--pill-pink)",
  "var(--pill-mint)",
  "var(--pill-blue)",
  "var(--pill-purple)",
  "var(--pill-orange)",
  "var(--pill-teal)",
];

export async function Navbar() {
  const [session, customPages] = await Promise.all([
    getSession(),
    prisma.page.findMany({
      where: { showInNav: true },
      orderBy: { sortOrder: "asc" },
      select: { slug: true, title: true },
    }),
  ]);
  const isStaff = session?.role === "ADMIN" || session?.role === "INSTRUCTOR";

  const links = [
    ...NAV_LINKS,
    ...customPages.map((p, i) => ({
      href: `/strony/${p.slug}`,
      label: p.title,
      color: CUSTOM_PAGE_COLORS[i % CUSTOM_PAGE_COLORS.length],
    })),
  ];

  return (
    <header className="relative sticky top-0 z-30 border-b border-[var(--border)] bg-[var(--surface)]/95 backdrop-blur">
      <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
        <Link href="/" className="flex items-center gap-2 transition-transform hover:scale-105">
          <Logo size="sm" align="start" />
          <span className="hidden text-xs font-semibold text-[var(--muted)] sm:block">
            Pracownia
            <br />
            kreatywno-edukacyjna
          </span>
        </Link>

        <NavPills links={links} className="hidden flex-wrap items-center gap-2 lg:flex" />

        <div className="flex items-center gap-3 text-sm">
          <HeartDoodle className="hidden h-4 w-4 xl:block" />
          {session ? (
            <>
              {isStaff ? (
                <Link
                  href="/admin"
                  className="hidden rounded-full border border-[var(--border)] px-3 py-1.5 transition-colors hover:bg-[var(--background)] sm:inline"
                >
                  Panel prowadzącego
                </Link>
              ) : (
                <Link
                  href="/panel"
                  className="hidden rounded-full border border-[var(--border)] px-3 py-1.5 transition-colors hover:bg-[var(--background)] sm:inline"
                >
                  Panel rodzica
                </Link>
              )}
              <span className="hidden text-[var(--muted)] xl:inline">{session.name}</span>
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
                className="hidden rounded-full border border-[var(--border)] px-3 py-1.5 transition-colors hover:bg-[var(--background)] sm:inline"
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

          <MobileMenu>
            <NavPills links={links} className="flex flex-col items-start gap-2" />
            <div className="mt-3 flex flex-col gap-2 border-t border-[var(--border)] pt-3">
              {isStaff && (
                <Link href="/admin" className="text-sm font-semibold">
                  Panel prowadzącego
                </Link>
              )}
              {session && !isStaff && (
                <Link href="/panel" className="text-sm font-semibold">
                  Panel rodzica
                </Link>
              )}
              {!session && (
                <Link href="/logowanie" className="text-sm font-semibold">
                  Zaloguj się
                </Link>
              )}
            </div>
          </MobileMenu>
        </div>
      </div>
    </header>
  );
}
