import { format } from "date-fns";
import { pl } from "date-fns/locale";
import type { SessionWithAvailability } from "@/lib/availability";
import type { ReactNode } from "react";

export function SessionCard({
  session,
  classTypeDescription,
  actions,
  adminMeta,
}: {
  session: SessionWithAvailability;
  /** Fallback description shown when the session has no day-specific override. */
  classTypeDescription?: string;
  actions?: ReactNode;
  /** Extra staff-only info (e.g. instructor, cancel button). */
  adminMeta?: ReactNode;
}) {
  const canceled = session.status === "CANCELED";
  const description = session.description || classTypeDescription;

  return (
    <div
      className={`rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5 ${
        canceled ? "opacity-60" : ""
      }`}
    >
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-2">
            <span
              className="h-2.5 w-2.5 rounded-full"
              style={{ backgroundColor: session.classType.color }}
              aria-hidden
            />
            <span className="text-xs font-semibold uppercase tracking-wide text-[var(--muted)]">
              {session.classType.name}
            </span>
            {canceled && (
              <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">
                Odwołane
              </span>
            )}
          </div>
          <h3 className="mt-1 text-lg font-bold">{session.title}</h3>
          <p className="text-sm text-[var(--muted)]">
            {format(session.startsAt, "EEEE d MMMM, HH:mm", { locale: pl })}–
            {format(session.endsAt, "HH:mm")} · prowadzi {session.instructorName}
          </p>
        </div>

        {!canceled && (
          <span
            className={`shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${
              session.isFull
                ? "bg-red-100 text-red-700"
                : session.spotsLeft <= 2
                  ? "bg-amber-100 text-amber-800"
                  : "bg-emerald-100 text-emerald-700"
            }`}
          >
            {session.isFull
              ? "Brak miejsc"
              : `${session.spotsLeft}/${session.capacity} wolnych miejsc`}
          </span>
        )}
      </div>

      {description && <p className="mt-3 text-sm">{description}</p>}

      {adminMeta}
      {actions && <div className="mt-4">{actions}</div>}
    </div>
  );
}
