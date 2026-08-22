import Link from "next/link";
import { format, addMonths, addWeeks, addDays } from "date-fns";
import { pl } from "date-fns/locale";
import { toDateParam, type CalendarView } from "@/lib/calendar-grid";

function hrefFor(
  basePath: string,
  view: CalendarView,
  date: Date,
  extra: Record<string, string | undefined>
) {
  const params = new URLSearchParams();
  params.set("view", view);
  params.set("date", toDateParam(date));
  for (const [k, v] of Object.entries(extra)) {
    if (v) params.set(k, v);
  }
  return `${basePath}?${params.toString()}`;
}

export function CalendarNav({
  basePath,
  view,
  anchor,
  extra = {},
}: {
  basePath: string;
  view: CalendarView;
  anchor: Date;
  extra?: Record<string, string | undefined>;
}) {
  const prevAnchor =
    view === "month" ? addMonths(anchor, -1) : view === "week" ? addWeeks(anchor, -1) : addDays(anchor, -1);
  const nextAnchor =
    view === "month" ? addMonths(anchor, 1) : view === "week" ? addWeeks(anchor, 1) : addDays(anchor, 1);

  const label =
    view === "month"
      ? format(anchor, "LLLL yyyy", { locale: pl })
      : view === "week"
        ? `Tydzień od ${format(anchor, "d MMMM", { locale: pl })}`
        : format(anchor, "EEEE, d MMMM yyyy", { locale: pl });

  return (
    <div className="flex flex-wrap items-center justify-between gap-3">
      <div className="flex items-center gap-2">
        <Link
          href={hrefFor(basePath, view, prevAnchor, extra)}
          className="rounded-full border border-[var(--border)] px-3 py-1.5 hover:bg-[var(--surface)]"
          aria-label="Wstecz"
        >
          ←
        </Link>
        <Link
          href={hrefFor(basePath, view, new Date(), extra)}
          className="rounded-full border border-[var(--border)] px-3 py-1.5 hover:bg-[var(--surface)]"
        >
          Dziś
        </Link>
        <Link
          href={hrefFor(basePath, view, nextAnchor, extra)}
          className="rounded-full border border-[var(--border)] px-3 py-1.5 hover:bg-[var(--surface)]"
          aria-label="Dalej"
        >
          →
        </Link>
        <span className="ml-2 font-semibold capitalize">{label}</span>
      </div>

      <div className="flex gap-1 rounded-full border border-[var(--border)] p-1">
        {(["month", "week", "day"] as const).map((v) => (
          <Link
            key={v}
            href={hrefFor(basePath, v, anchor, extra)}
            className={`rounded-full px-3 py-1 text-sm ${
              v === view
                ? "bg-[var(--primary)] text-[var(--primary-foreground)]"
                : "hover:bg-[var(--surface)]"
            }`}
          >
            {v === "month" ? "Miesiąc" : v === "week" ? "Tydzień" : "Dzień"}
          </Link>
        ))}
      </div>
    </div>
  );
}

export { hrefFor };
