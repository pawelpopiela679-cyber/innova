"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/db";
import {
  getSession,
  hashPassword,
  verifyPassword,
  setSessionCookie,
} from "@/lib/auth";
import { updateOwnProfileSchema } from "@/lib/validation";
import { saveUploadedImage, deleteUploadedFile } from "@/lib/upload";

/**
 * Lets a logged-in staff member (master admin OR instructor) edit their own
 * account — name, e-mail, avatar, bio and password. This is what an admin
 * uses to stop seeing the seed placeholder name "Właściciel Pracowni" and
 * set their own name/e-mail/hasło without touching the database directly.
 */
async function requireStaffSelf() {
  const session = await getSession();
  if (!session || (session.role !== "ADMIN" && session.role !== "INSTRUCTOR")) {
    redirect("/logowanie?next=/admin/profil");
  }
  return session!;
}

export async function updateOwnProfileAction(formData: FormData): Promise<void> {
  const session = await requireStaffSelf();

  const parsed = updateOwnProfileSchema.safeParse({
    name: String(formData.get("name") ?? ""),
    email: String(formData.get("email") ?? ""),
    bio: String(formData.get("bio") ?? ""),
    currentPassword: String(formData.get("currentPassword") ?? ""),
    newPassword: String(formData.get("newPassword") ?? ""),
  });
  if (!parsed.success) {
    redirect(
      `/admin/profil?error=${encodeURIComponent(
        parsed.error.issues[0]?.message ?? "Nieprawidłowe dane."
      )}`
    );
  }
  const { name, email, bio, currentPassword, newPassword } = parsed.data;

  const me = await prisma.user.findUnique({ where: { id: session.sub } });
  if (!me) {
    redirect("/logowanie?next=/admin/profil");
  }

  const passwordOk = await verifyPassword(currentPassword, me!.passwordHash);
  if (!passwordOk) {
    redirect(
      `/admin/profil?error=${encodeURIComponent("Obecne hasło jest nieprawidłowe.")}`
    );
  }

  const emailTaken = await prisma.user.findFirst({
    where: { email, NOT: { id: session.sub } },
  });
  if (emailTaken) {
    redirect(
      `/admin/profil?error=${encodeURIComponent("Ten adres e-mail jest już zajęty przez inne konto.")}`
    );
  }

  let avatarUrl = me!.avatarUrl;
  const photo = formData.get("photo");
  if (photo instanceof File && photo.size > 0) {
    try {
      const saved = await saveUploadedImage(photo, "instructors", session.sub);
      if (saved) {
        await deleteUploadedFile(me!.avatarUrl);
        avatarUrl = saved;
      }
    } catch (e) {
      redirect(
        `/admin/profil?error=${encodeURIComponent(
          e instanceof Error ? e.message : "Nie udało się zapisać zdjęcia."
        )}`
      );
    }
  }
  const removePhoto = formData.get("removePhoto") === "on";
  if (removePhoto && !photo) {
    await deleteUploadedFile(me!.avatarUrl);
    avatarUrl = null;
  }

  const updated = await prisma.user.update({
    where: { id: session.sub },
    data: {
      name,
      email,
      bio: bio || null,
      avatarUrl,
      ...(newPassword ? { passwordHash: await hashPassword(newPassword) } : {}),
    },
  });

  // Refresh the session cookie so the navbar/greeting reflects the new
  // name/e-mail immediately, without requiring a re-login.
  await setSessionCookie({
    sub: updated.id,
    email: updated.email,
    name: updated.name,
    role: updated.role,
  });

  revalidatePath("/", "layout");
  revalidatePath("/poznaj-nas");
  redirect("/admin/profil?saved=1");
}
