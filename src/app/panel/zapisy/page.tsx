import Link from "next/link";
import { format } from "date-fns";
import { pl } from "date-fns/locale";
import { getSession } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { cancelEnrollmentAction } from "@/lib/actions/enrollment-actions";

const STATUS_LABEL: Record<string, string> = {
  CONFIRMED: "Potwierdzony",
  WAITLIST: "Lista rezerwowa",
  CANCELED: "Anulowany",
};

const STATUS_CLASS: Record<string, string> = {
  CONFIRMED: "bg-emerald-100 text-emerald-700",
  WAITLIST: "bg-amber-100 text-amber-800",
  CANCELED: "bg-gray-200 text-gray-600",
};

export default async function EnrollmentsPage({
  searchParams,
}: {
  searchParams: Promise<{ info?: string; canceled?: string }>;
}) {
  const sp = await searchParams;
  const session = await getSession();
  if (!session) return null;

  const enrollments = await prisma.enrollment.findMany({
    where: { parentId: session.sub },
    include: { child: true, session: { include: { classType: true } } },
    orderBy: { session: { startsAt: "desc" } },
  });

  return (
    <div>
      <h1 className="text-2xl font-extrabold">Moje zapisy</h1>

      {sp.info && (
        <p className="mt-4 rounded-lg bg-amber-50 px-4 py-2 text-sm text-amber-800">{sp.info}</p>
      )}
      {sp.canceled && (
        <p className="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
          Zapis został anulowany.
        </p>
      )}

      {enrollments.length === 0 ? (
        <p className="mt-4 text-sm text-[var(--muted)]">
          Brak zapisów.{" "}
          <Link href="/kalendarz" className="text-[var(--primary)] underline">
            Przeglądaj kalendarz zajęć
          </Link>
          .
        </p>
      ) : (
        <div className="mt-6 space-y-3">
          {enrollments.map((e) => {
            const isPast = e.session.startsAt < new Date();
            return (
              <div
                key={e.id}
                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--border)] bg-[var(--surface)] p-4"
              >
                <div>
                  <div className="flex items-center gap-2">
                    <span
                      className="h-2 w-2 rounded-full"
                      style={{ backgroundColor: e.session.classType.color }}
                    />
                    <p className="font-semibold">
                      {e.session.classType.name} — {e.session.title}
                    </p>
                    <span
                      className={`rounded-full px-2 py-0.5 text-xs font-semibold ${STATUS_CLASS[e.status]}`}
                    >
                      {STATUS_LABEL[e.status]}
                    </span>
                  </div>
                  <p className="mt-1 text-sm text-[var(--muted)]">
                    {e.child.firstName} {e.child.lastName} ·{" "}
                    {format(e.session.startsAt, "d MMMM yyyy, HH:mm", { locale: pl })}
                  </p>
                </div>
                {e.status !== "CANCELED" && !isPast && (
                  <form action={cancelEnrollmentAction}>
                    <input type="hidden" name="enrollmentId" value={e.id} />
                    <button
                      type="submit"
                      className="rounded-full border border-[var(--border)] px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
                    >
                      Anuluj zapis
                    </button>
                  </form>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
