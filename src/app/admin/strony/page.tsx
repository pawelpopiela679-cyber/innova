import Link from "next/link";
import { redirect } from "next/navigation";
import { getSession } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { createPageAction, deletePageAction } from "@/lib/actions/page-actions";

export default async function PagesAdminPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string; added?: string; updated?: string; deleted?: string }>;
}) {
  const sp = await searchParams;
  const session = await getSession();
  if (!session || session.role !== "ADMIN") {
    redirect("/admin");
  }

  const pages = await prisma.page.findMany({ orderBy: { sortOrder: "asc" } });

  return (
    <div className="max-w-3xl">
      <h1 className="text-2xl font-extrabold">Strony</h1>
      <p className="mt-1 text-[var(--muted)]">
        Dodawaj własne podstrony (np. „Regulamin”, „FAQ”) bez potrzeby edycji kodu. Strona{" "}
        <Link href="/poznaj-nas" className="underline">
          Poznaj nas
        </Link>{" "}
        jest wbudowana na stałe i aktualizuje się automatycznie z listy prowadzących.
      </p>

      {sp.error && (
        <p className="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{sp.error}</p>
      )}
      {sp.added && (
        <p className="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
          Strona została dodana.
        </p>
      )}
      {sp.updated && (
        <p className="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
          Strona została zaktualizowana.
        </p>
      )}
      {sp.deleted && (
        <p className="mt-4 rounded-lg bg-gray-100 px-4 py-2 text-sm text-gray-700">
          Strona została usunięta.
        </p>
      )}

      <div className="mt-6 space-y-3">
        {pages.length === 0 && (
          <p className="text-sm text-[var(--muted)]">Nie masz jeszcze żadnych dodatkowych stron.</p>
        )}
        {pages.map((p) => (
          <div
            key={p.id}
            className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--border)] bg-[var(--surface)] p-4"
          >
            <div>
              <p className="font-semibold">
                {p.title}{" "}
                {p.showInNav && (
                  <span className="ml-2 rounded-full bg-[var(--sage-soft)] px-2 py-0.5 text-xs font-semibold text-[var(--sage)]">
                    w menu
                  </span>
                )}
              </p>
              <p className="text-sm text-[var(--muted)]">/strony/{p.slug}</p>
            </div>
            <div className="flex gap-2">
              <Link
                href={`/strony/${p.slug}`}
                target="_blank"
                className="rounded-full border border-[var(--border)] px-3 py-1.5 text-sm hover:bg-[var(--background)]"
              >
                Podgląd
              </Link>
              <Link
                href={`/admin/strony/${p.id}/edytuj`}
                className="rounded-full border border-[var(--border)] px-3 py-1.5 text-sm hover:bg-[var(--background)]"
              >
                Edytuj
              </Link>
              <form action={deletePageAction}>
                <input type="hidden" name="id" value={p.id} />
                <button
                  type="submit"
                  className="rounded-full border border-[var(--border)] px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
                >
                  Usuń
                </button>
              </form>
            </div>
          </div>
        ))}
      </div>

      <div className="mt-8 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6">
        <h2 className="font-bold">Dodaj nową stronę</h2>
        <form action={createPageAction} className="mt-4 grid gap-4">
          <div>
            <label htmlFor="title" className="text-sm font-medium">
              Tytuł
            </label>
            <input
              id="title"
              name="title"
              required
              placeholder="np. Regulamin zajęć"
              className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
            />
          </div>
          <div>
            <label htmlFor="slug" className="text-sm font-medium">
              Adres strony (bez spacji i polskich znaków)
            </label>
            <input
              id="slug"
              name="slug"
              required
              placeholder="np. regulamin"
              pattern="[a-z0-9-]+"
              className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
            />
            <p className="mt-1 text-xs text-[var(--muted)]">
              Strona będzie dostępna pod adresem: /strony/adres
            </p>
          </div>
          <div>
            <label htmlFor="content" className="text-sm font-medium">
              Treść
            </label>
            <textarea
              id="content"
              name="content"
              required
              rows={8}
              placeholder="Pisz akapitami — pusta linia zaczyna nowy akapit."
              className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
            />
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" name="showInNav" defaultChecked />
            Pokaż w menu nawigacji
          </label>
          <div>
            <button
              type="submit"
              className="rounded-full bg-[var(--sage)] px-5 py-2.5 font-semibold text-white hover:opacity-90"
            >
              Dodaj stronę
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
