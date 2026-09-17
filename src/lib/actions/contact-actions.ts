"use server";

import { redirect } from "next/navigation";
import { contactSchema } from "@/lib/validation";
import { sendMail } from "@/lib/mailer";

function redirectBack(basePath: string, params: Record<string, string>): never {
  const url = new URL(basePath, "http://internal");
  for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v);
  redirect(url.pathname + url.search);
}

export async function submitContactAction(formData: FormData): Promise<void> {
  const raw = {
    name: String(formData.get("name") ?? ""),
    email: String(formData.get("email") ?? ""),
    subject: String(formData.get("subject") ?? ""),
    message: String(formData.get("message") ?? ""),
    consent: String(formData.get("consent") ?? ""),
  };

  const parsed = contactSchema.safeParse(raw);
  if (!parsed.success) {
    redirectBack("/kontakt", { error: parsed.error.issues[0]?.message ?? "Nieprawidłowe dane." });
  }

  const { name, email, subject, message } = parsed.data;
  const notifyEmail = process.env.STUDIO_NOTIFY_EMAIL;

  if (notifyEmail) {
    await sendMail({
      to: notifyEmail.split(",").map((s) => s.trim()),
      subject: `Wiadomość ze strony: ${subject || "Kontakt"}`,
      text: `Nowa wiadomość z formularza kontaktowego.\n\nOd: ${name} <${email}>\nTemat: ${subject || "(brak)"}\n\n${message}`,
      html: `<div style="font-family: sans-serif; max-width: 480px;">
        <p><strong>Nowa wiadomość z formularza kontaktowego.</strong></p>
        <p>Od: ${escapeHtml(name)} &lt;${escapeHtml(email)}&gt;<br/>Temat: ${escapeHtml(subject || "(brak)")}</p>
        <p style="white-space: pre-wrap;">${escapeHtml(message)}</p>
      </div>`,
    });
  }

  redirect("/kontakt?success=1");
}

function escapeHtml(input: string): string {
  return input.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}
