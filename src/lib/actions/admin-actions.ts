"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { createSessionSchema } from "@/lib/validation";

async function requireStaff() {
  const session = await getSession();
  if (!session || (session.role !== "ADMIN" && session.role !== "INSTRUCTOR")) {
    redirect("/logowanie?next=/admin");
  }
  return session!;
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
  };

  const parsed = createSessionSchema.safeParse(raw);
  if (!parsed.success) {
    redirect(
      `/admin/zajecia/nowe?error=${encodeURIComponent(
        parsed.error.issues[0]?.message ?? "Nieprawidłowe dane."
      )}`
    );
  }

  const { classTypeId, title, date, startTime, endTime, capacity, instructorName, meetingUrl, description } =
    parsed.data;

  const startsAt = new Date(`${date}T${startTime}:00`);
  const endsAt = new Date(`${date}T${endTime}:00`);

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

  revalidatePath("/admin");
  revalidatePath("/kalendarz");
  redirect("/admin?added=1");
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
