import { prisma } from "@/lib/db";
import { sendEnrollmentConfirmationEmail } from "@/lib/mailer";

/**
 * When a CONFIRMED spot opens up (cancellation, or a staff reassignment
 * moves someone out), promote the earliest WAITLIST enrollment for that
 * session into CONFIRMED and email the parent. Shared by the parent's own
 * cancel action and the staff review actions so the behavior stays
 * consistent everywhere a confirmed seat is freed.
 */
export async function promoteNextWaitlisted(sessionId: string): Promise<void> {
  const nextInLine = await prisma.enrollment.findFirst({
    where: { sessionId, status: "WAITLIST" },
    orderBy: { createdAt: "asc" },
    include: { child: true, parent: true, session: { include: { classType: true } } },
  });
  if (!nextInLine) return;

  await prisma.enrollment.update({
    where: { id: nextInLine.id },
    data: { status: "CONFIRMED", confirmedAt: new Date() },
  });

  await sendEnrollmentConfirmationEmail({
    parentEmail: nextInLine.parent.email,
    parentName: nextInLine.parent.name,
    childName: `${nextInLine.child.firstName} ${nextInLine.child.lastName}`,
    classTypeName: nextInLine.session.classType.name,
    sessionTitle: nextInLine.session.title,
    startsAt: nextInLine.session.startsAt,
    endsAt: nextInLine.session.endsAt,
    instructorName: nextInLine.session.instructorName,
    meetingUrl: nextInLine.session.meetingUrl,
    waitlisted: false,
  });
}
