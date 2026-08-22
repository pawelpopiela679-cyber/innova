import Link from "next/link";
import { buildMonthGrid, isSameMonth, isSameDay, toDateParam } from "@/lib/calendar-grid";
import type { SessionWithAvailability } from "@/lib/availability";

const WEEKDAY_LABELS = ["Pon", "Wt", "Śr", "Czw", "Pt", "Sob", "Nd"];

export function MonthGrid({
  anchor,
  sessions,
  dayHref,
  showFreeSpots = false,
}: {
  anchor: Date;
  sessions: SessionWithAvailability[];
  dayHref: (dateStr: string) => string;
  /** When true (admin/instructor view), show total free spots per day instead of just dots. */
  showFreeSpots?: boolean;
}) {
  const weeks = buildMonthGrid(anchor);
  const today = new Date();

  const byDay = new Map<string, SessionWithAvailability[]>();
  for (const s of sessions) {
    const key = toDateParam(s.startsAt);
    if (!byDay.has(key)) byDay.set(key, []);
    byDay.get(key)!.push(s);
  }

  return (
    <div className="overflow-x-auto">
      <div className="grid min-w-[640px] grid-cols-7 gap-px rounded-xl border border-[var(--border)] bg-[var(--border)] text-sm">
        {WEEKDAY_LABELS.map((d) => (
          <div
            key={d}
            className="bg-[var(--surface)] px-2 py-2 text-center font-semibold text-[var(--muted)]"
          >
            {d}
          </div>
        ))}
        {weeks.flat().map((day) => {
          const key = toDateParam(day);
          const daySessions = (byDay.get(key) ?? []).filter((s) => s.status === "SCHEDULED");
          const inMonth = isSameMonth(day, anchor);
          const isToday = isSameDay(day, today);
          const freeSpots = daySessions.reduce((sum, s) => sum + s.spotsLeft, 0);

          return (
            <Link
              key={key}
              href={dayHref(key)}
              className={`min-h-[92px] bg-[var(--surface)] p-2 hover:bg-[var(--background)] ${
                inMonth ? "" : "opacity-40"
              }`}
            >
              <div
                className={`mb-1 inline-flex h-6 w-6 items-center justify-center rounded-full text-xs ${
                  isToday ? "bg-[var(--primary)] font-bold text-[var(--primary-foreground)]" : ""
                }`}
              >
                {day.getDate()}
              </div>

              {showFreeSpots ? (
                daySessions.length > 0 && (
                  <div className="space-y-0.5">
                    <div
                      className={`text-[11px] font-semibold ${
                        freeSpots === 0 ? "text-red-500" : "text-emerald-600"
                      }`}
                    >
                      {freeSpots === 0 ? "brak miejsc" : `${freeSpots} wolnych`}
                    </div>
                    <div className="text-[11px] text-[var(--muted)]">
                      {daySessions.length} zajęć
                    </div>
                  </div>
                )
              ) : (
                <>
                  <div className="flex flex-wrap gap-1">
                    {daySessions.slice(0, 5).map((s) => (
                      <span
                        key={s.id}
                        className="h-2 w-2 rounded-full"
                        style={{ backgroundColor: s.classType.color }}
                        title={s.classType.name}
                      />
                    ))}
                  </div>
                  {daySessions.length > 0 && (
                    <div className="mt-1 text-[11px] text-[var(--muted)]">
                      {daySessions.length} zajęć
                    </div>
                  )}
                </>
              )}
            </Link>
          );
        })}
      </div>
    </div>
  );
}
