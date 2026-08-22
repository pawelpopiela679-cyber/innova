"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { childSchema } from "@/lib/validation";

export async function createChildAction(formData: FormData): Promise<void> {
  const session = await getSession();
  if (!session) redirect("/logowanie?next=/panel/dzieci");

  const raw = {
    firstName: String(formData.get("firstName") ?? ""),
    lastName: String(formData.get("lastName") ?? ""),
    birthDate: String(formData.get("birthDate") ?? ""),
    notes: String(formData.get("notes") ?? ""),
  };

  const parsed = childSchema.safeParse(raw);
  if (!parsed.success) {
    redirect(
      `/panel/dzieci?error=${encodeURIComponent(
        parsed.error.issues[0]?.message ?? "Nieprawidłowe dane."
      )}`
    );
  }

  await prisma.child.create({
    data: {
      parentId: session!.sub,
      firstName: parsed.data.firstName,
      lastName: parsed.data.lastName,
      birthDate: new Date(parsed.data.birthDate),
      notes: parsed.data.notes || null,
    },
  });

  revalidatePath("/panel/dzieci");
  redirect("/panel/dzieci?added=1");
}

export async function deleteChildAction(formData: FormData): Promise<void> {
  const session = await getSession();
  if (!session) redirect("/logowanie?next=/panel/dzieci");

  const childId = String(formData.get("childId") ?? "");
  const child = await prisma.child.findUnique({ where: { id: childId } });
  if (child && child.parentId === session!.sub) {
    await prisma.child.delete({ where: { id: childId } });
  }

  revalidatePath("/panel/dzieci");
  redirect("/panel/dzieci?removed=1");
}
