import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { updateOwnProfileAction } from "@/lib/actions/profile-actions";

export default async function MyProfilePage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string; saved?: string }>;
}) {
  const { error, saved } = await searchParams;
  const session = await getSession();
  if (!session || (session.role !== "ADMIN" && session.role !== "INSTRUCTOR")) {
    redirect("/logowanie?next=/admin/profil");
  }

  const me = await prisma.user.findUnique({ where: { id: session!.sub } });
  if (!me) redirect("/logowanie?next=/admin/profil");

  return (
    <div className="mx-auto max-w-xl">
      <h1 className="font-heading text-2xl font-extrabold">Mój profil</h1>
      <p className="mt-1 text-sm text-[var(--muted)]">
        {me!.role === "ADMIN"
          ? "To jest Twoje konto master admina (właściciela pracowni). Tutaj zmienisz swoją nazwę wyświetlaną, e-mail, zdjęcie i hasło."
          : "Tutaj zmienisz swoje dane widoczne na stronie „Poznaj nas”, e-mail i hasło."}
      </p>

      {error && (
        <p className="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{error}</p>
      )}
      {saved && (
        <p className="mt-4 rounded-lg bg-[var(--sage-soft)] px-4 py-2 text-sm text-[var(--foreground)]">
          Zapisano zmiany.
        </p>
      )}

      <form
        action={updateOwnProfileAction}
        encType="multipart/form-data"
        className="mt-6 space-y-4 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6"
      >
        <div className="flex items-center gap-4">
          {me!.avatarUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={me!.avatarUrl}
              alt=""
              className="h-16 w-16 rounded-full object-cover border border-[var(--border)]"
            />
          ) : (
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-[var(--sage-soft)] font-heading text-xl font-bold">
              {me!.name.charAt(0).toUpperCase()}
            </div>
          )}
          <div className="flex-1">
            <label className="block text-sm font-semibold" htmlFor="photo">
              Zdjęcie profilowe
            </label>
            <input
              id="photo"
              name="photo"
              type="file"
              accept="image/png,image/jpeg,image/webp,image/gif"
              className="mt-1 block w-full text-sm"
            />
            {me!.avatarUrl && (
              <label className="mt-1 flex items-center gap-1.5 text-xs text-[var(--muted)]">
                <input type="checkbox" name="removePhoto" /> Usuń obecne zdjęcie
              </label>
            )}
          </div>
        </div>

        <div>
          <label className="block text-sm font-semibold" htmlFor="name">
            Imię i nazwisko / nazwa wyświetlana
          </label>
          <input
            id="name"
            name="name"
            defaultValue={me!.name}
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
          />
        </div>

        <div>
          <label className="block text-sm font-semibold" htmlFor="email">
            E-mail (login)
          </label>
          <input
            id="email"
            name="email"
            type="email"
            defaultValue={me!.email}
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
          />
        </div>

        <div>
          <label className="block text-sm font-semibold" htmlFor="bio">
            Krótka notka (widoczna na „Poznaj nas”)
          </label>
          <textarea
            id="bio"
            name="bio"
            defaultValue={me!.bio ?? ""}
            rows={3}
            maxLength={600}
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
          />
        </div>

        <hr className="border-[var(--border)]" />

        <div>
          <label className="block text-sm font-semibold" htmlFor="newPassword">
            Nowe hasło (zostaw puste, żeby nie zmieniać)
          </label>
          <input
            id="newPassword"
            name="newPassword"
            type="password"
            minLength={8}
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
          />
        </div>

        <div>
          <label className="block text-sm font-semibold" htmlFor="currentPassword">
            Obecne hasło (wymagane, żeby potwierdzić zmiany)
          </label>
          <input
            id="currentPassword"
            name="currentPassword"
            type="password"
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2"
          />
        </div>

        <button
          type="submit"
          className="rounded-full bg-[var(--primary)] px-5 py-2 font-semibold text-[var(--primary-foreground)] shadow-sm transition-transform hover:scale-105"
        >
          Zapisz zmiany
        </button>
      </form>
    </div>
  );
}
