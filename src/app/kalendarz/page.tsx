import Link from "next/link";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
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
import { enrollAction } from "@/lib/actions/enrollment-actions";
import { format } from "date-fns";
import { pl } from "date-fns/locale";

type SearchParams = {
  view?: string;
  date?: string;
  classType?: string;
  error?: string;
};

export default async function CalendarPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const sp = await searchParams;
  const view: CalendarView =
    sp.view === "week" || sp.view === "day" ? sp.view : "month";
  const anchor = parseDateParam(sp.date);
  const { from, to } = rangeForView(view, anchor);

  const [classTypes, sessions, session] = await Promise.all([
    prisma.classType.findMany({ orderBy: { createdAt: "asc" } }),
    getSessionsWithAvailability(from, to, { classTypeId: sp.classType || undefined }),
    getSession(),
  ]);

  const children = session
    ? await prisma.child.findMany({ where: { parentId: session.sub }, orderBy: { firstName: "asc" } })
    : [];

  const extra = { classType: sp.classType };

  return (
    <div className="mx-auto max-w-5xl px-4 py-10">
      <h1 className="text-3xl font-extrabold">Kalendarz zajęć</h1>
      <p className="mt-1 text-[var(--muted)]">
        Wybierz dzień, żeby zobaczyć opis zajęć i zapisać dziecko.
      </p>

      {sp.error && (
        <p className="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{sp.error}</p>
      )}

      <div className="mt-6 flex flex-wrap items-center gap-3">
        <form method="get" className="flex items-center gap-2 text-sm">
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
      </div>

      <div className="mt-6">
        <CalendarNav basePath="/kalendarz" view={view} anchor={anchor} extra={extra} />
      </div>

      <div className="mt-6">
        {view === "month" && (
          <MonthGrid
            anchor={anchor}
            sessions={sessions}
            dayHref={(dateStr) => hrefFor("/kalendarz", "day", new Date(`${dateStr}T00:00:00`), extra)}
          />
        )}

        {view === "week" && (
          <WeekView anchor={anchor} sessions={sessions} extra={extra} />
        )}

        {view === "day" && (
          <DayView
            anchor={anchor}
            sessions={sessions.filter((s) => s.status === "SCHEDULED")}
            isLoggedIn={!!session}
            kids={children}
          />
        )}
      </div>
    </div>
  );
}

function WeekView({
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
          .filter((s) => s.status === "SCHEDULED" && toDateParam(s.startsAt) === dateStr)
          .sort((a, b) => a.startsAt.getTime() - b.startsAt.getTime());
        return (
          <div key={dateStr} className="rounded-xl border border-[var(--border)] bg-[var(--surface)] p-3">
            <Link
              href={hrefFor("/kalendarz", "day", day, extra)}
              className="text-sm font-semibold capitalize hover:text-[var(--primary)]"
            >
              {format(day, "EEEE d.MM", { locale: pl })}
            </Link>
            <div className="mt-2 space-y-2">
              {daySessions.length === 0 && (
                <p className="text-xs text-[var(--muted)]">Brak zajęć</p>
              )}
              {daySessions.map((s) => (
                <Link
                  key={s.id}
                  href={hrefFor("/kalendarz", "day", day, extra)}
                  className="block rounded-lg border border-[var(--border)] px-2 py-1.5 text-xs hover:bg-[var(--background)]"
                >
                  <span
                    className="mr-1 inline-block h-1.5 w-1.5 rounded-full align-middle"
                    style={{ backgroundColor: s.classType.color }}
                  />
                  {format(s.startsAt, "HH:mm")} {s.title}
                  <div className={s.isFull ? "text-red-600" : "text-emerald-600"}>
                    {s.isFull ? "brak miejsc" : `${s.spotsLeft} wolnych`}
                  </div>
                </Link>
              ))}
            </div>
          </div>
        );
      })}
    </div>
  );
}

function DayView({
  anchor,
  sessions,
  isLoggedIn,
  kids,
}: {
  anchor: Date;
  sessions: Awaited<ReturnType<typeof getSessionsWithAvailability>>;
  isLoggedIn: boolean;
  kids: { id: string; firstName: string; lastName: string }[];
}) {
  if (sessions.length === 0) {
    return (
      <p className="rounded-xl border border-dashed border-[var(--border)] p-8 text-center text-[var(--muted)]">
        Brak zajęć w tym dniu ({format(anchor, "d MMMM yyyy", { locale: pl })}).
      </p>
    );
  }

  return (
    <div className="space-y-4">
      {sessions
        .sort((a, b) => a.startsAt.getTime() - b.startsAt.getTime())
        .map((s) => (
          <SessionCard
            key={s.id}
            session={s}
            actions={
              !isLoggedIn ? (
                <Link
                  href={`/logowanie?next=${encodeURIComponent(
                    `/kalendarz?view=day&date=${toDateParam(anchor)}`
                  )}`}
                  className="inline-block rounded-full bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-[var(--primary-foreground)]"
                >
                  Zaloguj się, aby zapisać dziecko
                </Link>
              ) : kids.length === 0 ? (
                <Link
                  href="/panel/dzieci"
                  className="inline-block rounded-full bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-[var(--primary-foreground)]"
                >
                  Dodaj dziecko, aby się zapisać
                </Link>
              ) : (
                <div>
                  <form action={enrollAction} className="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="sessionId" value={s.id} />
                    <select
                      name="childId"
                      required
                      className="rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-1.5 text-sm"
                    >
                      {kids.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.firstName} {c.lastName}
                        </option>
                      ))}
                    </select>
                    <button
                      type="submit"
                      className="rounded-full bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-[var(--primary-foreground)] hover:opacity-90"
                    >
                      Zgłoś chęć zapisu
                    </button>
                  </form>
                  <p className="mt-1.5 text-xs text-[var(--muted)]">
                    Zgłoszenie wymaga potwierdzenia przez pracownię — dobierzemy grupę odpowiednią
                    do wieku dziecka i odpowiemy e-mailem.
                  </p>
                </div>
              )
            }
          />
        ))}
    </div>
  );
}
