import { prisma } from "@/lib/db";
import { getSessionsWithAvailability } from "@/lib/availability";
import {
  buildWeekDays,
  parseDateParam,
  rangeForView,
  toDateParam,
  type CalendarView,
} from "@/lib/calendar-grid";
import { CalendarNav, hrefFor } from "@/components/calendar/calendar-nav";
import { MonthGrid } from "@/components/calendar/month-grid";
import { SessionCard } from "@/components/calendar/session-card";
import { cancelSessionAction } from "@/lib/actions/admin-actions";
import { format } from "date-fns";
import { pl } from "date-fns/locale";

type SearchParams = {
  view?: string;
  date?: string;
  classType?: string;
};

export default async function AdminAvailabilityPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const sp = await searchParams;
  const view: CalendarView = sp.view === "week" || sp.view === "day" ? sp.view : "month";
  const anchor = parseDateParam(sp.date);
  const { from, to } = rangeForView(view, anchor);

  const [classTypes, sessions] = await Promise.all([
    prisma.classType.findMany({ orderBy: { createdAt: "asc" } }),
    getSessionsWithAvailability(from, to, { classTypeId: sp.classType || undefined }),
  ]);

  const extra = { classType: sp.classType };
  const scheduled = sessions.filter((s) => s.status === "SCHEDULED");
  const totalFree = scheduled.reduce((sum, s) => sum + s.spotsLeft, 0);
  const totalCapacity = scheduled.reduce((sum, s) => sum + s.capacity, 0);

  return (
    <div>
      <h1 className="text-2xl font-extrabold">Dostępność terminów</h1>
      <p className="mt-1 text-[var(--muted)]">
        Podgląd wolnych miejsc na dany dzień, tydzień i miesiąc — dla wszystkich prowadzących.
      </p>

      <div className="mt-4 flex flex-wrap gap-4 text-sm">
        <span className="rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-700">
          Wolne miejsca w widoku: {totalFree}/{totalCapacity}
        </span>
        <span className="rounded-full bg-[var(--surface)] px-3 py-1 font-semibold border border-[var(--border)]">
          Zaplanowanych zajęć: {scheduled.length}
        </span>
      </div>

      <form method="get" className="mt-4 flex items-center gap-2 text-sm">
        <input type="hidden" name="view" value={view} />
        <input type="hidden" name="date" value={toDateParam(anchor)} />
        <label htmlFor="classType" className="text-[var(--muted)]">
          Rodzaj zajęć:
        </label>
        <select
          id="classType"
          name="classType"
          defaultValue={sp.classType ?? ""}
          className="rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-1.5"
        >
          <option value="">Wszystkie</option>
          {classTypes.map((ct) => (
            <option key={ct.id} value={ct.id}>
              {ct.name}
            </option>
          ))}
        </select>
        <button
          type="submit"
          className="rounded-lg border border-[var(--border)] px-3 py-1.5 hover:bg-[var(--surface)]"
        >
          Filtruj
        </button>
      </form>

      <div className="mt-6">
        <CalendarNav basePath="/admin" view={view} anchor={anchor} extra={extra} />
      </div>

      <div className="mt-6">
        {view === "month" && (
          <MonthGrid
            anchor={anchor}
            sessions={sessions}
            showFreeSpots
            dayHref={(dateStr) => hrefFor("/admin", "day", new Date(`${dateStr}T00:00:00`), extra)}
          />
        )}

        {view === "week" && <AdminWeekView anchor={anchor} sessions={scheduled} extra={extra} />}

        {view === "day" && (
          <div className="space-y-4">
            {scheduled.length === 0 && (
              <p className="rounded-xl border border-dashed border-[var(--border)] p-8 text-center text-[var(--muted)]">
                Brak zajęć w tym dniu.
              </p>
            )}
            {scheduled
              .sort((a, b) => a.startsAt.getTime() - b.startsAt.getTime())
              .map((s) => (
                <SessionCard
                  key={s.id}
                  session={s}
                  adminMeta={
                    <p className="mt-2 text-sm text-[var(--muted)]">
                      Zapisanych: {s.confirmedCount}/{s.capacity} · prowadzi {s.instructorName}
                    </p>
                  }
                  actions={
                    <form action={cancelSessionAction}>
                      <input type="hidden" name="sessionId" value={s.id} />
                      <button
                        type="submit"
                        className="rounded-full border border-[var(--border)] px-4 py-1.5 text-sm text-red-600 hover:bg-red-50"
                      >
                        Odwołaj zajęcia
                      </button>
                    </form>
                  }
                />
              ))}
          </div>
        )}
      </div>
    </div>
  );
}

function AdminWeekView({
  anchor,
  sessions,
  extra,
}: {
  anchor: Date;
  sessions: Awaited<ReturnType<typeof getSessionsWithAvailability>>;
  extra: Record<string, string | undefined>;
}) {
  const days = buildWeekDays(anchor);
  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-7">
      {days.map((day) => {
        const dateStr = toDateParam(day);
        const daySessions = sessions
          .filter((s) => toDateParam(s.startsAt) === dateStr)
          .sort((a, b) => a.startsAt.getTime() - b.startsAt.getTime());
        const freeSpots = daySessions.reduce((sum, s) => sum + s.spotsLeft, 0);
        return (
          <a
            key={dateStr}
            href={hrefFor("/admin", "day", day, extra)}
            className="rounded-xl border border-[var(--border)] bg-[var(--surface)] p-3 hover:bg-[var(--background)]"
          >
            <p className="text-sm font-semibold capitalize">
              {format(day, "EEEE d.MM", { locale: pl })}
            </p>
            <p
              className={`mt-1 text-xs font-semibold ${
                freeSpots === 0 && daySessions.length > 0 ? "text-red-500" : "text-emerald-600"
              }`}
            >
              {daySessions.length === 0 ? "brak zajęć" : `${freeSpots} wolnych miejsc`}
            </p>
            <div className="mt-2 space-y-1">
              {daySessions.map((s) => (
                <div key={s.id} className="text-xs">
                  <span
                    className="mr-1 inline-block h-1.5 w-1.5 rounded-full align-middle"
                    style={{ backgroundColor: s.classType.color }}
                  />
                  {format(s.startsAt, "HH:mm")} {s.classType.name} ({s.confirmedCount}/{s.capacity})
                </div>
              ))}
            </div>
          </a>
        );
      })}
    </div>
  );
}
