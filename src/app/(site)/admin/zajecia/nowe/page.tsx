import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { createSessionAction } from "@/lib/actions/admin-actions";

export default async function NewSessionPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string }>;
}) {
  const sp = await searchParams;
  const session = await getSession();
  const classTypes = await prisma.classType.findMany({ orderBy: { createdAt: "asc" } });

  return (
    <div className="max-w-2xl">
      <h1 className="text-2xl font-extrabold">Nowe zajęcia</h1>
      <p className="mt-1 text-[var(--muted)]">
        Podaj dzień i godzinę pierwszych zajęć — jeśli to cykl cotygodniowy (np. „co
        środę o 17:00 przez 10 tygodni”), ustaw liczbę tygodni poniżej, a resztę terminów
        dodamy automatycznie w tych samych godzinach.
      </p>

      {sp.error && (
        <p className="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{sp.error}</p>
      )}

      <form action={createSessionAction} className="mt-6 grid gap-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <label htmlFor="classTypeId" className="text-sm font-medium">
            Rodzaj zajęć
          </label>
          <select
            id="classTypeId"
            name="classTypeId"
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          >
            {classTypes.map((ct) => (
              <option key={ct.id} value={ct.id}>
                {ct.name}
              </option>
            ))}
          </select>
        </div>

        <div className="sm:col-span-2">
          <label htmlFor="title" className="text-sm font-medium">
            Nazwa zajęć
          </label>
          <input
            id="title"
            name="title"
            required
            placeholder="np. Robotyka — grupa online"
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>

        <div>
          <label htmlFor="date" className="text-sm font-medium">
            Data pierwszych zajęć
          </label>
          <input
            id="date"
            name="date"
            type="date"
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>
        <div>
          <label htmlFor="capacity" className="text-sm font-medium">
            Liczba miejsc (maks. 10)
          </label>
          <input
            id="capacity"
            name="capacity"
            type="number"
            min={1}
            max={10}
            defaultValue={10}
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>

        <div>
          <label htmlFor="startTime" className="text-sm font-medium">
            Godzina rozpoczęcia
          </label>
          <input
            id="startTime"
            name="startTime"
            type="time"
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>
        <div>
          <label htmlFor="endTime" className="text-sm font-medium">
            Godzina zakończenia
          </label>
          <input
            id="endTime"
            name="endTime"
            type="time"
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>

        <div className="sm:col-span-2">
          <label htmlFor="weeksCount" className="text-sm font-medium">
            Powtórz co tydzień, przez ile tygodni?
          </label>
          <input
            id="weeksCount"
            name="weeksCount"
            type="number"
            min={1}
            max={20}
            defaultValue={10}
            required
            className="mt-1 w-full max-w-[10rem] rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
          <p className="mt-1 text-xs text-[var(--muted)]">
            Wpisz 1, jeśli to jednorazowe zajęcia (np. odrabianie zaległości), bez powtórzeń.
          </p>
        </div>

        <div className="sm:col-span-2">
          <label htmlFor="instructorName" className="text-sm font-medium">
            Prowadzący
          </label>
          <input
            id="instructorName"
            name="instructorName"
            defaultValue={session?.name ?? ""}
            required
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>

        <div className="sm:col-span-2">
          <label htmlFor="meetingUrl" className="text-sm font-medium">
            Link do zajęć online (opcjonalnie)
          </label>
          <input
            id="meetingUrl"
            name="meetingUrl"
            placeholder="https://meet..."
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>

        <div className="sm:col-span-2">
          <label htmlFor="description" className="text-sm font-medium">
            Opis zajęć tego dnia (opcjonalnie, nadpisuje ogólny opis)
          </label>
          <textarea
            id="description"
            name="description"
            rows={3}
            className="mt-1 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2"
          />
        </div>

        <div className="sm:col-span-2">
          <button
            type="submit"
            className="rounded-full bg-[var(--primary)] px-5 py-2.5 font-semibold text-[var(--primary-foreground)] hover:opacity-90"
          >
            Dodaj do kalendarza
          </button>
        </div>
      </form>
    </div>
  );
}
