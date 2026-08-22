import Link from "next/link";
import { notFound } from "next/navigation";
import { format } from "date-fns";
import { pl } from "date-fns/locale";
import { getSession } from "@/lib/auth";
import { prisma } from "@/lib/db";

export default async function EnrollmentConfirmationPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  const session = await getSession();
  if (!session) return null;

  const enrollment = await prisma.enrollment.findUnique({
    where: { id },
    include: { child: true, session: { include: { classType: true } } },
  });

  if (!enrollment || enrollment.parentId !== session.sub) notFound();

  const waitlisted = enrollment.status === "WAITLIST";

  return (
    <div className="mx-auto max-w-lg px-4 py-16 text-center">
      <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-3xl">
        {waitlisted ? "⏳" : "✅"}
      </div>
      <h1 className="mt-4 text-2xl font-extrabold">
        {waitlisted ? "Dodano do listy rezerwowej" : "Zapis potwierdzony!"}
      </h1>
      <p className="mt-2 text-[var(--muted)]">
        {waitlisted
          ? "Grupa jest obecnie pełna. Odezwiemy się, jeśli zwolni się miejsce."
          : "Wysłaliśmy potwierdzenie na Twój e-mail. Poinformowaliśmy również prowadzącego zajęcia."}
      </p>

      <div className="mt-6 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-6 text-left">
        <div className="flex items-center gap-2">
          <span
            className="h-2.5 w-2.5 rounded-full"
            style={{ backgroundColor: enrollment.session.classType.color }}
          />
          <span className="text-sm font-semibold uppercase text-[var(--muted)]">
            {enrollment.session.classType.name}
          </span>
        </div>
        <h2 className="mt-1 text-lg font-bold">{enrollment.session.title}</h2>
        <p className="mt-1 text-sm">
          {format(enrollment.session.startsAt, "EEEE d MMMM yyyy, HH:mm", { locale: pl })}–
          {format(enrollment.session.endsAt, "HH:mm")}
        </p>
        <p className="mt-1 text-sm text-[var(--muted)]">
          Dziecko: {enrollment.child.firstName} {enrollment.child.lastName}
        </p>
        <p className="text-sm text-[var(--muted)]">
          Prowadzący: {enrollment.session.instructorName}
        </p>
        {!waitlisted && enrollment.session.meetingUrl && (
          <p className="mt-3 text-sm">
            Link do zajęć online:{" "}
            <a href={enrollment.session.meetingUrl} className="text-[var(--primary)] underline">
              {enrollment.session.meetingUrl}
            </a>
          </p>
        )}
      </div>

      <div className="mt-8 flex justify-center gap-3">
        <Link
          href="/panel/zapisy"
          className="rounded-full bg-[var(--primary)] px-5 py-2.5 font-semibold text-[var(--primary-foreground)]"
        >
          Moje zapisy
        </Link>
        <Link
          href="/kalendarz"
          className="rounded-full border border-[var(--border)] px-5 py-2.5 font-semibold hover:bg-[var(--surface)]"
        >
          Zapisz na kolejne zajęcia
        </Link>
      </div>
    </div>
  );
}
