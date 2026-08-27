import Link from "next/link";
import { redirect } from "next/navigation";
import { format } from "date-fns";
import { pl } from "date-fns/locale";
import { getSession } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { createInstructorAction, deleteInstructorAction } from "@/lib/actions/staff-actions";

const ROLE_LABEL: Record<string, string> = {
  ADMIN: "Właściciel (master admin)",
  INSTRUCTOR: "Prowadzący",
};

export default async function StaffPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string; added?: string; updated?: string; deleted?: string }>;
}) {
  const sp = await searchParams;
  const session = await getSession();
  if (!session || session.role !== "ADMIN") {
    redirect("/admin");
  }

  const staff = await prisma.user.findMany({
    where: { role: { in: ["ADMIN", "INSTRUCTOR"] } },
    orderBy: [{ role: "asc" }, { name: "asc" }],
  });

  return (
    <div className="max-w-3xl">
      <h1 className="text-2xl font-extrabold">Prowadzący i konta</h1>
      <p className="mt-1 text-[var(--muted)]">
        Jako właściciel pracowni (master admin) możesz zakładać, edytować i usuwać konta
        prowadzących — po zalogowaniu każdy prowadzący sam dodaje swój grafik w{" "}
        <span className="font-semibold">+ Nowe zajęcia</span>.
      </p>

      {sp.error && (
        <p className="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{sp.error}</p>
      )}
      {sp.added && (
        <p className="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
          Konto prowadzącego zostało utworzone — przekaż mu e-mail i hasło.
        </p>
      )}
      {sp.updated && (
        <p className="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
          Dane prowadzącego zostały zaktualizowane.
        </p>
      )}
      {sp.deleted && (
        <p className="mt-4 rounded-lg bg-gray-100 px-4 py-2 text-sm text-gray-700">
          Konto prowadzącego zostało usunięte. Zaplanowane zajęcia zostają w kalendarzu.
        </p>
      )}

      <div className="mt-6 space-y-3">
        {staff.map((u) => (
          <div
            key={u.id}
            className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--border)] bg-[var(--surface)] p-4"
          >
            <div className="flex items-center gap-3">
              {u.avatarUrl ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  src={u.avatarUrl}
                  alt={u.name}
                  className="h-12 w-12 rounded-full object-cover"
                />
              ) : (
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[var(--sage-soft)] text-lg font-bold text-[var(--sage)]">
                  {u.name.charAt(0)}
                </div>
              )}
              <div>
                <p className="font-semibold">
                  {u.name}{" "}
                  <span className="ml-2 rounded-full bg-[var(--sage-soft)] px-2 py-0.5 text-xs font-semibold text-[var(--sage)]">
                    {ROLE_LABEL[u.role] ?? u.role}
                  </span>
                </p>
                <p className="text-sm text-[var(--muted)]">{u.email}</p>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <p className="text-xs text-[var(--muted)]">
                Konto od {format(u.createdAt, "d MMMM yyyy", { locale: pl })}
              </p>
              {u.role === "INSTRUCTOR" && (
                <>
                  <Link
                    href={`/admin/prowadzacy/${u.id}/edytuj`}
                    className="rounded-full border border-[var(--border)] px-3 py-1.5 text-sm hover:bg-[var(--background)]"
                  >
                    Edytuj
                  </Link>
                  <form action={deleteInstructorAction}>
                    <input type="hidden" name="userId" value={u.id} />
                    <button
                      type="submit"
                      className="rounded-full border border-[var(--border)] px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
                    >
                      Usuń
                    </button>
                  </form>
                </>
              )}
            </div>
          </div>
        ))}
      </div>

      <div className="mt-8 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6">
        <h2 className="font-bold">Dodaj konto prowadzącego</h2>
        <p className="mt-1 text-sm text-[var(--muted)]">
          Ustaw hasło startowe i przekaż je prowadzącemu — może je potem zmienić w edycji konta.
        </p>
        <form
          action={createInstructorAction}
          className="mt-4 grid gap-4 sm:grid-cols-2"
          encType="multipart/form-data"
        >
          <div>
            <label htmlFor="name" className="text-sm font-medium">
              Imię i nazwisko
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
              E-mail (login)
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
            <label htmlFor="password" className="text-sm font-medium">
              Hasło startowe
            </label>
            <input
              id="password"
              name="password"
              type="text"
              required
              minLength={8}
              placeholder="co najmniej 8 znaków"
              className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
            />
          </div>
          <div>
            <label htmlFor="photo" className="text-sm font-medium">
              Zdjęcie (opcjonalnie)
            </label>
            <input
              id="photo"
              name="photo"
              type="file"
              accept="image/png,image/jpeg,image/webp,image/gif"
              className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm"
            />
          </div>
          <div className="sm:col-span-2">
            <label htmlFor="bio" className="text-sm font-medium">
              Krótka notka na stronę „Poznaj nas” (opcjonalnie)
            </label>
            <textarea
              id="bio"
              name="bio"
              rows={2}
              maxLength={600}
              className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
            />
          </div>
          <div className="sm:col-span-2">
            <button
              type="submit"
              className="rounded-full bg-[var(--sage)] px-5 py-2.5 font-semibold text-white hover:opacity-90"
            >
              Utwórz konto prowadzącego
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
