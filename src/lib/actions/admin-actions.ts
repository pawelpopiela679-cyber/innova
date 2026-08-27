"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { addWeeks } from "date-fns";
import { prisma } from "@/lib/db";
import { getSession, hashPassword } from "@/lib/auth";
import { createSessionSchema, createInstructorSchema } from "@/lib/validation";
import {
  sendEnrollmentConfirmationEmail,
  sendEnrollmentDeclinedEmail,
} from "@/lib/mailer";
import { promoteNextWaitlisted } from "@/lib/enrollment-helpers";

async function requireStaff() {
  const session = await getSession();
  if (!session || (session.role !== "ADMIN" && session.role !== "INSTRUCTOR")) {
    redirect("/logowanie?next=/admin");
  }
  return session!;
}

/** Only the studio owner ("master admin") manages staff accounts — instructors can't create other instructors. */
async function requireAdmin() {
  const session = await getSession();
  if (!session || session.role !== "ADMIN") {
    redirect("/logowanie?next=/admin/prowadzacy");
  }
  return session!;
}

export async function createInstructorAction(formData: FormData): Promise<void> {
  await requireAdmin();

  const parsed = createInstructorSchema.safeParse({
    name: String(formData.get("name") ?? ""),
    email: String(formData.get("email") ?? ""),
    password: String(formData.get("password") ?? ""),
  });
  if (!parsed.success) {
    redirect(
      `/admin/prowadzacy?error=${encodeURIComponent(
        parsed.error.issues[0]?.message ?? "Nieprawidłowe dane."
      )}`
    );
  }

  const existing = await prisma.user.findUnique({ where: { email: parsed.data.email } });
  if (existing) {
    redirect(
      `/admin/prowadzacy?error=${encodeURIComponent("Konto z tym adresem e-mail już istnieje.")}`
    );
  }

  await prisma.user.create({
    data: {
      name: parsed.data.name,
      email: parsed.data.email,
      passwordHash: await hashPassword(parsed.data.password),
      role: "INSTRUCTOR",
    },
  });

  revalidatePath("/admin/prowadzacy");
  redirect("/admin/prowadzacy?added=1");
}

export async function createSessionAction(formData: FormData): Promise<void> {
  const staff = await requireStaff();

  const raw = {
    classTypeId: String(formData.get("classTypeId") ?? ""),
    title: String(formData.get("title") ?? ""),
    date: String(formData.get("date") ?? ""),
    startTime: String(formData.get("startTime") ?? ""),
    endTime: String(formData.get("endTime") ?? ""),
    capacity: String(formData.get("capacity") ?? ""),
    instructorName: String(formData.get("instructorName") ?? staff.name),
    meetingUrl: String(formData.get("meetingUrl") ?? ""),
    description: String(formData.get("description") ?? ""),
    weeksCount: String(formData.get("weeksCount") ?? "1"),
  };

  const parsed = createSessionSchema.safeParse(raw);
  if (!parsed.success) {
    redirect(
      `/admin/zajecia/nowe?error=${encodeURIComponent(
        parsed.error.issues[0]?.message ?? "Nieprawidłowe dane."
      )}`
    );
  }

  const {
    classTypeId,
    title,
    date,
    startTime,
    endTime,
    capacity,
    instructorName,
    meetingUrl,
    description,
    weeksCount,
  } = parsed.data;

  const firstStartsAt = new Date(`${date}T${startTime}:00`);
  const firstEndsAt = new Date(`${date}T${endTime}:00`);
  const durationMs = firstEndsAt.getTime() - firstStartsAt.getTime();

  // Repeats the same weekly slot for `weeksCount` weeks in one go — an
  // instructor filling in their own recurring schedule doesn't have to
  // submit the form 10 times for a 10-week series.
  for (let i = 0; i < weeksCount; i++) {
    const startsAt = addWeeks(firstStartsAt, i);
    const endsAt = new Date(startsAt.getTime() + durationMs);
    await prisma.classSession.create({
      data: {
        classTypeId,
        title,
        startsAt,
        endsAt,
        capacity,
        instructorName,
        instructorId: staff.role === "INSTRUCTOR" ? staff.sub : null,
        meetingUrl: meetingUrl || null,
        description: description || null,
      },
    });
  }

  revalidatePath("/admin");
  revalidatePath("/kalendarz");
  redirect(`/admin?added=${weeksCount}`);
}

export async function cancelSessionAction(formData: FormData): Promise<void> {
  await requireStaff();
  const sessionId = String(formData.get("sessionId") ?? "");

  await prisma.classSession.update({
    where: { id: sessionId },
    data: { status: "CANCELED" },
  });

  revalidatePath("/admin");
  revalidatePath("/kalendarz");
  redirect("/admin?canceled=1");
}

/**
 * Staff review: confirm a request into a specific group (the one the parent
 * originally picked, or a different one of the same class type chosen from
 * the dropdown — e.g. because the child's age fits another group better).
 */
export async function confirmEnrollmentAction(formData: FormData): Promise<void> {
  await requireStaff();

  const enrollmentId = String(formData.get("enrollmentId") ?? "");
  const targetSessionId = String(formData.get("sessionId") ?? "");

  const enrollment = await prisma.enrollment.findUnique({
    where: { id: enrollmentId },
    include: { child: true, parent: true, session: { include: { classType: true } } },
  });
  if (!enrollment) redirect("/admin/zapisy?error=Nie+znaleziono+zgłoszenia");

  const targetSession = await prisma.classSession.findUnique({
    where: { id: targetSessionId },
    include: { classType: true },
  });
  if (!targetSession || targetSession.classTypeId !== enrollment!.session.classTypeId) {
    redirect(
      `/admin/zapisy?error=${encodeURIComponent("Wybrana grupa musi być tego samego rodzaju zajęć.")}`
    );
  }

  const previousSessionId = enrollment!.sessionId;
  const previousStatus = enrollment!.status;

  await prisma.enrollment.update({
    where: { id: enrollmentId },
    data: { sessionId: targetSessionId, status: "CONFIRMED", confirmedAt: new Date() },
  });

  // If this reassigns someone out of a group they were already confirmed in,
  // free up their old seat for the next person on that group's waitlist.
  if (previousStatus === "CONFIRMED" && previousSessionId !== targetSessionId) {
    await promoteNextWaitlisted(previousSessionId);
  }

  await sendEnrollmentConfirmationEmail({
    parentEmail: enrollment!.parent.email,
    parentName: enrollment!.parent.name,
    childName: `${enrollment!.child.firstName} ${enrollment!.child.lastName}`,
    classTypeName: targetSession!.classType.name,
    sessionTitle: targetSession!.title,
    startsAt: targetSession!.startsAt,
    endsAt: targetSession!.endsAt,
    instructorName: targetSession!.instructorName,
    meetingUrl: targetSession!.meetingUrl,
    waitlisted: false,
  });

  revalidatePath("/admin/zapisy");
  revalidatePath("/panel/zapisy");
  redirect("/admin/zapisy?confirmed=1");
}

/** Staff review: put a request on the waitlist for its currently-selected group (e.g. it's full). */
export async function waitlistEnrollmentAction(formData: FormData): Promise<void> {
  await requireStaff();
  const enrollmentId = String(formData.get("enrollmentId") ?? "");

  const enrollment = await prisma.enrollment.findUnique({
    where: { id: enrollmentId },
    include: { child: true, parent: true, session: { include: { classType: true } } },
  });
  if (!enrollment) redirect("/admin/zapisy?error=Nie+znaleziono+zgłoszenia");

  await prisma.enrollment.update({
    where: { id: enrollmentId },
    data: { status: "WAITLIST" },
  });

  await sendEnrollmentConfirmationEmail({
    parentEmail: enrollment!.parent.email,
    parentName: enrollment!.parent.name,
    childName: `${enrollment!.child.firstName} ${enrollment!.child.lastName}`,
    classTypeName: enrollment!.session.classType.name,
    sessionTitle: enrollment!.session.title,
    startsAt: enrollment!.session.startsAt,
    endsAt: enrollment!.session.endsAt,
    instructorName: enrollment!.session.instructorName,
    meetingUrl: enrollment!.session.meetingUrl,
    waitlisted: true,
  });

  revalidatePath("/admin/zapisy");
  revalidatePath("/panel/zapisy");
  redirect("/admin/zapisy?waitlisted=1");
}

/** Staff review: decline a request (or remove an existing enrollment entirely). */
export async function declineEnrollmentAction(formData: FormData): Promise<void> {
  await requireStaff();
  const enrollmentId = String(formData.get("enrollmentId") ?? "");

  const enrollment = await prisma.enrollment.findUnique({
    where: { id: enrollmentId },
    include: { child: true, parent: true, session: { include: { classType: true } } },
  });
  if (!enrollment) redirect("/admin/zapisy?error=Nie+znaleziono+zgłoszenia");

  const wasConfirmed = enrollment!.status === "CONFIRMED";

  await prisma.enrollment.update({
    where: { id: enrollmentId },
    data: { status: "CANCELED", canceledAt: new Date() },
  });

  if (wasConfirmed) {
    await promoteNextWaitlisted(enrollment!.sessionId);
  }

  await sendEnrollmentDeclinedEmail({
    parentEmail: enrollment!.parent.email,
    parentName: enrollment!.parent.name,
    childName: `${enrollment!.child.firstName} ${enrollment!.child.lastName}`,
    classTypeName: enrollment!.session.classType.name,
    sessionTitle: enrollment!.session.title,
  });

  revalidatePath("/admin/zapisy");
  revalidatePath("/panel/zapisy");
  redirect("/admin/zapisy?declined=1");
}
