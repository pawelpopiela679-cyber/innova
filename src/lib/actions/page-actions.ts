"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { pageSchema } from "@/lib/validation";

async function requireAdmin() {
  const session = await getSession();
  if (!session || session.role !== "ADMIN") {
    redirect("/logowanie?next=/admin/strony");
  }
  return session!;
}

export async function createPageAction(formData: FormData): Promise<void> {
  await requireAdmin();

  const parsed = pageSchema.safeParse({
    title: String(formData.get("title") ?? ""),
    slug: String(formData.get("slug") ?? ""),
    content: String(formData.get("content") ?? ""),
    showInNav: formData.get("showInNav") === "on",
  });
  if (!parsed.success) {
    redirect(
      `/admin/strony?error=${encodeURIComponent(parsed.error.issues[0]?.message ?? "Nieprawidłowe dane.")}`
    );
  }

  const existing = await prisma.page.findUnique({ where: { slug: parsed.data.slug } });
  if (existing) {
    redirect(`/admin/strony?error=${encodeURIComponent("Strona z tym adresem już istnieje.")}`);
  }

  const count = await prisma.page.count();
  await prisma.page.create({
    data: { ...parsed.data, sortOrder: count },
  });

  revalidatePath("/", "layout");
  redirect("/admin/strony?added=1");
}

export async function updatePageAction(formData: FormData): Promise<void> {
  await requireAdmin();
  const id = String(formData.get("id") ?? "");

  const parsed = pageSchema.safeParse({
    title: String(formData.get("title") ?? ""),
    slug: String(formData.get("slug") ?? ""),
    content: String(formData.get("content") ?? ""),
    showInNav: formData.get("showInNav") === "on",
  });
  if (!parsed.success) {
    redirect(
      `/admin/strony/${id}/edytuj?error=${encodeURIComponent(
        parsed.error.issues[0]?.message ?? "Nieprawidłowe dane."
      )}`
    );
  }

  const slugTaken = await prisma.page.findFirst({ where: { slug: parsed.data.slug, NOT: { id } } });
  if (slugTaken) {
    redirect(
      `/admin/strony/${id}/edytuj?error=${encodeURIComponent("Strona z tym adresem już istnieje.")}`
    );
  }

  await prisma.page.update({ where: { id }, data: parsed.data });

  revalidatePath("/", "layout");
  redirect("/admin/strony?updated=1");
}

export async function deletePageAction(formData: FormData): Promise<void> {
  await requireAdmin();
  const id = String(formData.get("id") ?? "");

  await prisma.page.delete({ where: { id } }).catch(() => null);

  revalidatePath("/", "layout");
  redirect("/admin/strony?deleted=1");
}
