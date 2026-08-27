"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { enrollSchema } from "@/lib/validation";
import { sendEnrollmentPendingEmail, sendStudioNewSignupNotification } from "@/lib/mailer";
import { promoteNextWaitlisted } from "@/lib/enrollment-helpers";

/**
 * Parent-facing: submits a *request* to join a group. This never books a
 * seat outright — every request starts as PENDING and needs staff review
 * (see admin-actions.ts), who check the child's age against the group and
 * confirm into the requested group or reassign to a better-fitting one.
 */
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
    redirect(`/panel/zapisy?info=${encodeURIComponent("Dziecko ma już zgłoszenie na te zajęcia.")}`);
  }

  const enrollment = already
    ? await prisma.enrollment.update({
        where: { id: already.id },
        data: { status: "PENDING", canceledAt: null, confirmedAt: null },
      })
    : await prisma.enrollment.create({
        data: { sessionId, childId, parentId: session!.sub, status: "PENDING" },
      });

  const parent = await prisma.user.findUniqueOrThrow({ where: { id: session!.sub } });

  await Promise.all([
    sendEnrollmentPendingEmail({
      parentEmail: parent.email,
      parentName: parent.name,
      childName: `${child.firstName} ${child.lastName}`,
      classTypeName: classSession!.classType.name,
      sessionTitle: classSession!.title,
      startsAt: classSession!.startsAt,
      endsAt: classSession!.endsAt,
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
      confirmedCount: classSession!.enrollments.length,
      capacity: classSession!.capacity,
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
  });

  if (enrollment && enrollment.parentId === session!.sub && enrollment.status !== "CANCELED") {
    const wasConfirmed = enrollment.status === "CONFIRMED";
    await prisma.enrollment.update({
      where: { id: enrollment.id },
      data: { status: "CANCELED", canceledAt: new Date() },
    });

    if (wasConfirmed) {
      await promoteNextWaitlisted(enrollment.sessionId);
    }
  }

  revalidatePath("/panel/zapisy");
  revalidatePath("/kalendarz");
  redirect("/panel/zapisy?canceled=1");
}
