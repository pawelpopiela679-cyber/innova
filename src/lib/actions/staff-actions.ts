"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/db";
import { getSession, hashPassword } from "@/lib/auth";
import { createInstructorSchema, updateInstructorSchema } from "@/lib/validation";
import { saveUploadedImage, deleteUploadedFile } from "@/lib/upload";

/** Only the studio owner ("master admin") manages staff accounts. */
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
    bio: String(formData.get("bio") ?? ""),
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

  const user = await prisma.user.create({
    data: {
      name: parsed.data.name,
      email: parsed.data.email,
      bio: parsed.data.bio || null,
      passwordHash: await hashPassword(parsed.data.password),
      role: "INSTRUCTOR",
    },
  });

  const photo = formData.get("photo");
  if (photo instanceof File) {
    try {
      const avatarUrl = await saveUploadedImage(photo, "instructors", user.id);
      if (avatarUrl) {
        await prisma.user.update({ where: { id: user.id }, data: { avatarUrl } });
      }
    } catch (e) {
      // Account is already created; a bad photo shouldn't block that.
      redirect(
        `/admin/prowadzacy?added=1&error=${encodeURIComponent(
          e instanceof Error ? e.message : "Nie udało się zapisać zdjęcia."
        )}`
      );
    }
  }

  revalidatePath("/admin/prowadzacy");
  redirect("/admin/prowadzacy?added=1");
}

export async function updateInstructorAction(formData: FormData): Promise<void> {
  await requireAdmin();

  const parsed = updateInstructorSchema.safeParse({
    userId: String(formData.get("userId") ?? ""),
    name: String(formData.get("name") ?? ""),
    email: String(formData.get("email") ?? ""),
    bio: String(formData.get("bio") ?? ""),
    newPassword: String(formData.get("newPassword") ?? ""),
  });
  if (!parsed.success) {
    redirect(
      `/admin/prowadzacy?error=${encodeURIComponent(
        parsed.error.issues[0]?.message ?? "Nieprawidłowe dane."
      )}`
    );
  }
  const { userId, name, email, bio, newPassword } = parsed.data;

  const target = await prisma.user.findUnique({ where: { id: userId } });
  if (!target || target.role !== "INSTRUCTOR") {
    redirect(`/admin/prowadzacy?error=${encodeURIComponent("Nie znaleziono prowadzącego.")}`);
  }

  const emailTaken = await prisma.user.findFirst({ where: { email, NOT: { id: userId } } });
  if (emailTaken) {
    redirect(
      `/admin/prowadzacy/${userId}/edytuj?error=${encodeURIComponent(
        "Ten adres e-mail jest już zajęty przez inne konto."
      )}`
    );
  }

  let avatarUrl = target!.avatarUrl;
  const photo = formData.get("photo");
  if (photo instanceof File && photo.size > 0) {
    try {
      const saved = await saveUploadedImage(photo, "instructors", userId);
      if (saved) {
        await deleteUploadedFile(target!.avatarUrl);
        avatarUrl = saved;
      }
    } catch (e) {
      redirect(
        `/admin/prowadzacy/${userId}/edytuj?error=${encodeURIComponent(
          e instanceof Error ? e.message : "Nie udało się zapisać zdjęcia."
        )}`
      );
    }
  }
  const removePhoto = formData.get("removePhoto") === "on";
  if (removePhoto && !photo) {
    await deleteUploadedFile(target!.avatarUrl);
    avatarUrl = null;
  }

  await prisma.user.update({
    where: { id: userId },
    data: {
      name,
      email,
      bio: bio || null,
      avatarUrl,
      ...(newPassword ? { passwordHash: await hashPassword(newPassword) } : {}),
    },
  });

  revalidatePath("/admin/prowadzacy");
  revalidatePath("/poznaj-nas");
  redirect("/admin/prowadzacy?updated=1");
}

export async function deleteInstructorAction(formData: FormData): Promise<void> {
  const admin = await requireAdmin();
  const userId = String(formData.get("userId") ?? "");

  if (userId === admin.sub) {
    redirect(`/admin/prowadzacy?error=${encodeURIComponent("Nie możesz usunąć własnego konta.")}`);
  }

  const target = await prisma.user.findUnique({ where: { id: userId } });
  if (!target || target.role !== "INSTRUCTOR") {
    redirect(`/admin/prowadzacy?error=${encodeURIComponent("Nie znaleziono prowadzącego.")}`);
  }

  // Sessions this instructor created stay on the calendar (instructorName is
  // stored separately as free text) — just detach the account reference.
  await prisma.classSession.updateMany({
    where: { instructorId: userId },
    data: { instructorId: null },
  });
  await deleteUploadedFile(target!.avatarUrl);
  await prisma.user.delete({ where: { id: userId } });

  revalidatePath("/admin/prowadzacy");
  revalidatePath("/poznaj-nas");
  redirect("/admin/prowadzacy?deleted=1");
}
