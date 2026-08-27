"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";

const HEX_RE = /^#[0-9a-fA-F]{6}$/;
const FIELDS = [
  "background",
  "foreground",
  "surface",
  "border",
  "primary",
  "primaryLight",
  "accent",
  "gold",
  "muted",
] as const;

export async function updateThemeAction(formData: FormData): Promise<void> {
  const session = await getSession();
  if (!session || session.role !== "ADMIN") {
    redirect("/logowanie?next=/admin/wyglad");
  }

  const data: Record<string, string> = {};
  for (const field of FIELDS) {
    const value = String(formData.get(field) ?? "");
    if (!HEX_RE.test(value)) {
      redirect(
        `/admin/wyglad?error=${encodeURIComponent(`Nieprawidłowy kolor dla pola „${field}”.`)}`
      );
    }
    data[field] = value;
  }

  await prisma.siteSettings.upsert({
    where: { id: "singleton" },
    update: data,
    create: { id: "singleton", ...data },
  });

  // The whole site reads these values (root layout), so a broad revalidation
  // is the simplest way to make sure every page picks up the new colors.
  revalidatePath("/", "layout");
  redirect("/admin/wyglad?saved=1");
}

export async function resetThemeAction(): Promise<void> {
  const session = await getSession();
  if (!session || session.role !== "ADMIN") {
    redirect("/logowanie?next=/admin/wyglad");
  }

  await prisma.siteSettings.deleteMany({ where: { id: "singleton" } });
  revalidatePath("/", "layout");
  redirect("/admin/wyglad?reset=1");
}
