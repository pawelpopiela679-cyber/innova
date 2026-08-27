import { prisma } from "@/lib/db";
import { calculateAge } from "@/lib/age";
import { format } from "date-fns";
import { pl } from "date-fns/locale";
import {
  confirmEnrollmentAction,
  waitlistEnrollmentAction,
  declineEnrollmentAction,
} from "@/lib/actions/admin-actions";

const STATUS_LABEL: Record<string, string> = {
  PENDING: "Oczekuje",
  CONFIRMED: "Potwierdzony",
  WAITLIST: "Lista rezerwowa",
  CANCELED: "Anulowany",
};

const STATUS_CLASS: Record<string, string> = {
  PENDING: "bg-sky-100 text-sky-700",
  CONFIRMED: "bg-emerald-100 text-emerald-700",
  WAITLIST: "bg-amber-100 text-amber-800",
  CANCELED: "bg-gray-200 text-gray-600",
};

const FILTERS = ["PENDING", "CONFIRMED", "WAITLIST", "CANCELED", "ALL"] as const;
const FILTER_LABEL: Record<(typeof FILTERS)[number], string> = {
  PENDING: "Oczekujące na potwierdzenie",
  CONFIRMED: "Potwierdzone",
  WAITLIST: "Lista rezerwowa",
  CANCELED: "Anulowane",
  ALL: "Wszystkie",
};

export default async function AdminEnrollmentsPage({
  searchParams,
}: {
  searchParams: Promise<{
    status?: string;
    confirmed?: string;
    waitlisted?: string;
    declined?: string;
    error?: string;
  }>;
}) {
  const sp = await searchParams;
  const filter = (FILTERS as readonly string[]).includes(sp.status ?? "")
    ? (sp.status as (typeof FILTERS)[number])
    : "PENDING";

  const enrollments = await prisma.enrollment.findMany({
    where: filter === "ALL" ? {} : { status: filter },
    include: {
      child: true,
      parent: true,
      session: { include: { classType: true, enrollments: { where: { status: "CONFIRMED" } } } },
    },
    orderBy: { createdAt: "asc" },
  });

  // All future, scheduled sessions grouped by class type — used to populate
  // the "reassign to another group" dropdown for each row.
  const allSessions = await prisma.classSession.findMany({
    where: { status: "SCHEDULED" },
    orderBy: { startsAt: "asc" },
  });
  const sessionsByClassType = new Map<string, typeof allSessions>();
  for (const s of allSessions) {
    const list = sessionsByClassType.get(s.classTypeId) ?? [];
    list.push(s);
    sessionsByClassType.set(s.classTypeId, list);
  }

  return (
    <div>
      <h1 className="text-2xl font-extrabold">Zgłoszenia i zapisy</h1>
      <p className="mt-1 text-[var(--muted)]">
        Sprawdź wiek dziecka, potwierdź zgłoszenie do wybranej grupy (lub przypisz inną, jeśli
        wiek pasuje lepiej gdzie indziej) — grupy max. 10 dzieci.
      </p>

      {sp.error && (
        <p className="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{sp.error}</p>
      )}
      {sp.confirmed && (
        <p className="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
          Zgłoszenie potwierdzone, rodzic dostał e-mail.
        </p>
      )}
      {sp.waitlisted && (
        <p className="mt-4 rounded-lg bg-amber-50 px-4 py-2 text-sm text-amber-800">
          Przeniesiono na listę rezerwową.
        </p>
      )}
      {sp.declined && (
        <p className="mt-4 rounded-lg bg-gray-100 px-4 py-2 text-sm text-gray-700">
          Zgłoszenie odrzucone/anulowane.
        </p>
      )}

      <div className="mt-6 flex flex-wrap gap-2">
        {FILTERS.map((f) => (
          <a
            key={f}
            href={`/admin/zapisy?status=${f}`}
            className={`rounded-full border px-3 py-1.5 text-sm ${
              f === filter
                ? "border-[var(--primary)] bg-[var(--primary)] text-white"
                : "border-[var(--border)] hover:bg-[var(--surface)]"
            }`}
          >
            {FILTER_LABEL[f]}
          </a>
        ))}
      </div>

      <div className="mt-6 space-y-4">
        {enrollments.length === 0 && (
          <p className="rounded-xl border border-dashed border-[var(--border)] p-8 text-center text-[var(--muted)]">
            Brak zgłoszeń w tej kategorii.
          </p>
        )}

        {enrollments.map((e) => {
          const age = calculateAge(e.child.birthDate);
          const groupOptions = sessionsByClassType.get(e.session.classTypeId) ?? [];
          const confirmedInCurrent = e.session.enrollments.length;

          return (
            <div
              key={e.id}
              className="rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div className="flex items-center gap-2">
                    <span
                      className="h-2 w-2 rounded-full"
                      style={{ backgroundColor: e.session.classType.color }}
                    />
                    <span className="text-xs font-semibold uppercase text-[var(--muted)]">
                      {e.session.classType.name}
                    </span>
                    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${STATUS_CLASS[e.status]}`}>
                      {STATUS_LABEL[e.status]}
                    </span>
                  </div>
                  <p className="mt-1 font-bold">
                    {e.child.firstName} {e.child.lastName}{" "}
                    <span className="font-normal text-[var(--muted)]">— {age} lat</span>
                  </p>
                  <p className="text-sm text-[var(--muted)]">
                    Rodzic: {e.parent.name} · {e.parent.email}
                    {e.parent.phone ? ` · ${e.parent.phone}` : ""}
                  </p>
                  <p className="mt-1 text-sm">
                    Zgłoszona grupa: <strong>{e.session.title}</strong> —{" "}
                    {format(e.session.startsAt, "EEEE d MMMM, HH:mm", { locale: pl })} · zajętość{" "}
                    {confirmedInCurrent}/{e.session.capacity}
                  </p>
                </div>
              </div>

              {e.status !== "CANCELED" && (
                <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-[var(--border)] pt-4">
                  <form action={confirmEnrollmentAction} className="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="enrollmentId" value={e.id} />
                    <select
                      name="sessionId"
                      defaultValue={e.sessionId}
                      className="rounded-lg border border-[var(--border)] bg-[var(--background)] px-2 py-1.5 text-sm"
                    >
                      {groupOptions.map((s) => (
                        <option key={s.id} value={s.id}>
                          {s.title} — {format(s.startsAt, "d.MM HH:mm", { locale: pl })}
                        </option>
                      ))}
                    </select>
                    <button
                      type="submit"
                      className="rounded-full bg-[var(--sage)] px-4 py-1.5 text-sm font-semibold text-white hover:opacity-90"
                    >
                      Potwierdź do tej grupy
                    </button>
                  </form>

                  <form action={waitlistEnrollmentAction}>
                    <input type="hidden" name="enrollmentId" value={e.id} />
                    <button
                      type="submit"
                      className="rounded-full border border-[var(--border)] px-4 py-1.5 text-sm hover:bg-[var(--background)]"
                    >
                      Lista rezerwowa
                    </button>
                  </form>

                  <form action={declineEnrollmentAction}>
                    <input type="hidden" name="enrollmentId" value={e.id} />
                    <button
                      type="submit"
                      className="rounded-full border border-[var(--border)] px-4 py-1.5 text-sm text-red-600 hover:bg-red-50"
                    >
                      Odrzuć / anuluj
                    </button>
                  </form>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
