"use server";

import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import {
  hashPassword,
  verifyPassword,
  setSessionCookie,
  clearSessionCookie,
} from "@/lib/auth";
import { registerSchema, loginSchema } from "@/lib/validation";

function redirectBack(basePath: string, params: Record<string, string>): never {
  const url = new URL(basePath, "http://internal");
  for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v);
  redirect(url.pathname + url.search);
}

export async function registerAction(formData: FormData): Promise<void> {
  const raw = {
    name: String(formData.get("name") ?? ""),
    email: String(formData.get("email") ?? ""),
    phone: String(formData.get("phone") ?? ""),
    password: String(formData.get("password") ?? ""),
  };
  const nextPath = String(formData.get("next") ?? "/panel");

  const parsed = registerSchema.safeParse(raw);
  if (!parsed.success) {
    redirectBack("/rejestracja", {
      error: parsed.error.issues[0]?.message ?? "Nieprawidłowe dane.",
    });
  }

  const existing = await prisma.user.findUnique({ where: { email: parsed.data.email } });
  if (existing) {
    redirectBack("/rejestracja", { error: "Konto z tym adresem e-mail już istnieje." });
  }

  const user = await prisma.user.create({
    data: {
      name: parsed.data.name,
      email: parsed.data.email,
      phone: parsed.data.phone || null,
      passwordHash: await hashPassword(parsed.data.password),
      role: "PARENT",
    },
  });

  await setSessionCookie({
    sub: user.id,
    email: user.email,
    name: user.name,
    role: user.role,
  });

  redirect(nextPath.startsWith("/") ? nextPath : "/panel");
}

export async function loginAction(formData: FormData): Promise<void> {
  const raw = {
    email: String(formData.get("email") ?? ""),
    password: String(formData.get("password") ?? ""),
  };
  const nextPath = String(formData.get("next") ?? "/panel");

  const parsed = loginSchema.safeParse(raw);
  if (!parsed.success) {
    redirectBack("/logowanie", {
      error: parsed.error.issues[0]?.message ?? "Nieprawidłowe dane.",
      next: nextPath,
    });
  }

  const user = await prisma.user.findUnique({ where: { email: parsed.data.email } });
  const valid = user ? await verifyPassword(parsed.data.password, user.passwordHash) : false;

  if (!user || !valid) {
    redirectBack("/logowanie", {
      error: "Nieprawidłowy e-mail lub hasło.",
      next: nextPath,
    });
  }

  await setSessionCookie({
    sub: user.id,
    email: user.email,
    name: user.name,
    role: user.role,
  });

  const target = nextPath.startsWith("/")
    ? nextPath
    : user.role === "ADMIN" || user.role === "INSTRUCTOR"
      ? "/admin"
      : "/panel";
  redirect(target);
}

export async function logoutAction(): Promise<void> {
  await clearSessionCookie();
  redirect("/");
}
