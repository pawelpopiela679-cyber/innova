import { notFound, redirect } from "next/navigation";
import { getSession } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { updateInstructorAction } from "@/lib/actions/staff-actions";

export default async function EditInstructorPage({
  params,
  searchParams,
}: {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ error?: string }>;
}) {
  const { id } = await params;
  const sp = await searchParams;
  const session = await getSession();
  if (!session || session.role !== "ADMIN") {
    redirect("/admin");
  }

  const instructor = await prisma.user.findUnique({ where: { id } });
  if (!instructor || instructor.role !== "INSTRUCTOR") notFound();

  return (
    <div className="max-w-xl">
      <h1 className="text-2xl font-extrabold">Edytuj: {instructor.name}</h1>

      {sp.error && (
        <p className="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{sp.error}</p>
      )}

      <div className="mt-6 flex items-center gap-4">
        {instructor.avatarUrl ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={instructor.avatarUrl}
            alt={instructor.name}
            className="h-16 w-16 rounded-full object-cover"
          />
        ) : (
          <div className="flex h-16 w-16 items-center justify-center rounded-full bg-[var(--sage-soft)] text-2xl font-bold text-[var(--sage)]">
            {instructor.name.charAt(0)}
          </div>
        )}
      </div>

      <form
        action={updateInstructorAction}
        className="mt-4 grid gap-4 sm:grid-cols-2"
        encType="multipart/form-data"
      >
        <input type="hidden" name="userId" value={instructor.id} />

        <div>
          <label htmlFor="name" className="text-sm font-medium">
            Imię i nazwisko
          </label>
          <input
            id="name"
            name="name"
            defaultValue={instructor.name}
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
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
            defaultValue={instructor.email}
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>

        <div>
          <label htmlFor="photo" className="text-sm font-medium">
            Nowe zdjęcie (opcjonalnie)
          </label>
          <input
            id="photo"
            name="photo"
            type="file"
            accept="image/png,image/jpeg,image/webp,image/gif"
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm"
          />
          {instructor.avatarUrl && (
            <label className="mt-2 flex items-center gap-2 text-sm text-[var(--muted)]">
              <input type="checkbox" name="removePhoto" />
              Usuń obecne zdjęcie
            </label>
          )}
        </div>
        <div>
          <label htmlFor="newPassword" className="text-sm font-medium">
            Nowe hasło (opcjonalnie)
          </label>
          <input
            id="newPassword"
            name="newPassword"
            type="text"
            minLength={8}
            placeholder="zostaw puste, aby nie zmieniać"
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>

        <div className="sm:col-span-2">
          <label htmlFor="bio" className="text-sm font-medium">
            Krótka notka na stronę „Poznaj nas”
          </label>
          <textarea
            id="bio"
            name="bio"
            rows={3}
            maxLength={600}
            defaultValue={instructor.bio ?? ""}
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>

        <div className="sm:col-span-2 flex gap-3">
          <button
            type="submit"
            className="rounded-full bg-[var(--sage)] px-5 py-2.5 font-semibold text-white hover:opacity-90"
          >
            Zapisz zmiany
          </button>
          <a
            href="/admin/prowadzacy"
            className="rounded-full border border-[var(--border)] px-5 py-2.5 font-semibold hover:bg-[var(--surface)]"
          >
            Anuluj
          </a>
        </div>
      </form>
    </div>
  );
}
