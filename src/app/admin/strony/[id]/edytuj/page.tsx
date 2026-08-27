import { notFound, redirect } from "next/navigation";
import { getSession } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { updatePageAction } from "@/lib/actions/page-actions";

export default async function EditPagePage({
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

  const page = await prisma.page.findUnique({ where: { id } });
  if (!page) notFound();

  return (
    <div className="max-w-2xl">
      <h1 className="text-2xl font-extrabold">Edytuj: {page.title}</h1>

      {sp.error && (
        <p className="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{sp.error}</p>
      )}

      <form action={updatePageAction} className="mt-6 grid gap-4">
        <input type="hidden" name="id" value={page.id} />
        <div>
          <label htmlFor="title" className="text-sm font-medium">
            Tytuł
          </label>
          <input
            id="title"
            name="title"
            defaultValue={page.title}
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>
        <div>
          <label htmlFor="slug" className="text-sm font-medium">
            Adres strony
          </label>
          <input
            id="slug"
            name="slug"
            defaultValue={page.slug}
            required
            pattern="[a-z0-9-]+"
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>
        <div>
          <label htmlFor="content" className="text-sm font-medium">
            Treść
          </label>
          <textarea
            id="content"
            name="content"
            defaultValue={page.content}
            required
            rows={10}
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" name="showInNav" defaultChecked={page.showInNav} />
          Pokaż w menu nawigacji
        </label>
        <div className="flex gap-3">
          <button
            type="submit"
            className="rounded-full bg-[var(--sage)] px-5 py-2.5 font-semibold text-white hover:opacity-90"
          >
            Zapisz zmiany
          </button>
          <a
            href="/admin/strony"
            className="rounded-full border border-[var(--border)] px-5 py-2.5 font-semibold hover:bg-[var(--surface)]"
          >
            Anuluj
          </a>
        </div>
      </form>
    </div>
  );
}
