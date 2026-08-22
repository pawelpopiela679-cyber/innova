"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { enrollSchema } from "@/lib/validation";
import {
  sendEnrollmentConfirmationEmail,
  sendStudioNewSignupNotification,
} from "@/lib/mailer";

export async function enrollAction(formData: FormData): Promise<void> {
  const session = await getSession();
  if (!session) redirect("/logowanie?next=/kalendarz");

  const parsed = enrollSchema.safeParse({
    sessionId: String(formData.get("sessionId") ?? ""),
    childId: String(formData.get("childId") ?? ""),
  });
  if (!parsed.success) {
    redirect(`/kalendarz?error=${encodeURIComponent("Wybierz dziecko.")}`);
  }
  const { sessionId, childId } = parsed.data;

  const child = await prisma.child.findUnique({ where: { id: childId } });
  if (!child || child.parentId !== session!.sub) {
    redirect(`/kalendarz?error=${encodeURIComponent("To nie jest Twoje dziecko.")}`);
  }

  const classSession = await prisma.classSession.findUnique({
    where: { id: sessionId },
    include: { classType: true, enrollments: { where: { status: "CONFIRMED" } } },
  });
  if (!classSession || classSession.status !== "SCHEDULED") {
    redirect(`/kalendarz?error=${encodeURIComponent("Te zajęcia nie są już dostępne.")}`);
  }

  const already = await prisma.enrollment.findUnique({
    where: { sessionId_childId: { sessionId, childId } },
  });
  if (already && already.status !== "CANCELED") {
    redirect(`/panel/zapisy?info=${encodeURIComponent("Dziecko jest już zapisane na te zajęcia.")}`);
  }

  const confirmedCount = classSession!.enrollments.length;
  const waitlisted = confirmedCount >= classSession!.capacity;

  const enrollment = already
    ? await prisma.enrollment.update({
        where: { id: already.id },
        data: {
          status: waitlisted ? "WAITLIST" : "CONFIRMED",
          canceledAt: null,
        },
      })
    : await prisma.enrollment.create({
        data: {
          sessionId,
          childId,
          parentId: session!.sub,
          status: waitlisted ? "WAITLIST" : "CONFIRMED",
        },
      });

  const parent = await prisma.user.findUniqueOrThrow({ where: { id: session!.sub } });
  const spotsLeftAfter = Math.max(
    classSession!.capacity - (confirmedCount + (waitlisted ? 0 : 1)),
    0
  );

  await Promise.all([
    sendEnrollmentConfirmationEmail({
      parentEmail: parent.email,
      parentName: parent.name,
      childName: `${child.firstName} ${child.lastName}`,
      classTypeName: classSession!.classType.name,
      sessionTitle: classSession!.title,
      startsAt: classSession!.startsAt,
      endsAt: classSession!.endsAt,
      instructorName: classSession!.instructorName,
      meetingUrl: classSession!.meetingUrl,
      waitlisted,
    }),
    sendStudioNewSignupNotification({
      childName: `${child.firstName} ${child.lastName}`,
      childBirthDate: child.birthDate,
      parentName: parent.name,
      parentEmail: parent.email,
      parentPhone: parent.phone,
      classTypeName: classSession!.classType.name,
      sessionTitle: classSession!.title,
      startsAt: classSession!.startsAt,
      endsAt: classSession!.endsAt,
      waitlisted,
      spotsLeftAfter,
    }),
  ]);

  revalidatePath("/panel/zapisy");
  revalidatePath("/kalendarz");
  redirect(`/panel/zapisy/${enrollment.id}/potwierdzenie`);
}

export async function cancelEnrollmentAction(formData: FormData): Promise<void> {
  const session = await getSession();
  if (!session) redirect("/logowanie?next=/panel/zapisy");

  const enrollmentId = String(formData.get("enrollmentId") ?? "");
  const enrollment = await prisma.enrollment.findUnique({
    where: { id: enrollmentId },
    include: { session: true },
  });

  if (enrollment && enrollment.parentId === session!.sub && enrollment.status !== "CANCELED") {
    const wasConfirmed = enrollment.status === "CONFIRMED";
    await prisma.enrollment.update({
      where: { id: enrollment.id },
      data: { status: "CANCELED", canceledAt: new Date() },
    });

    // Promote the earliest waitlisted enrollment for this session, if any.
    if (wasConfirmed) {
      const nextInLine = await prisma.enrollment.findFirst({
        where: { sessionId: enrollment.sessionId, status: "WAITLIST" },
        orderBy: { createdAt: "asc" },
        include: { child: true, parent: true, session: { include: { classType: true } } },
      });
      if (nextInLine) {
        await prisma.enrollment.update({
          where: { id: nextInLine.id },
          data: { status: "CONFIRMED" },
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
    }
  }

  revalidatePath("/panel/zapisy");
  revalidatePath("/kalendarz");
  redirect("/panel/zapisy?canceled=1");
}
