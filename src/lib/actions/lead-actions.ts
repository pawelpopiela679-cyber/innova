"use server";

import { redirect } from "next/navigation";
import { leadSchema } from "@/lib/validation";
import { sendMail } from "@/lib/mailer";

function redirectBack(basePath: string, params: Record<string, string>): never {
  const url = new URL(basePath, "http://internal");
  for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v);
  redirect(url.pathname + url.search);
}

/** Public "Zapisy" lead form — sends an inquiry to the studio, no account required. */
export async function submitLeadAction(formData: FormData): Promise<void> {
  const raw = {
    childName: String(formData.get("childName") ?? ""),
    childAge: String(formData.get("childAge") ?? ""),
    parentEmail: String(formData.get("parentEmail") ?? ""),
    parentPhone: String(formData.get("parentPhone") ?? ""),
    consent: String(formData.get("consent") ?? ""),
  };

  const parsed = leadSchema.safeParse(raw);
  if (!parsed.success) {
    redirectBack("/zapisy", { error: parsed.error.issues[0]?.message ?? "Nieprawidłowe dane." });
  }

  const { childName, childAge, parentEmail, parentPhone } = parsed.data;
  const notifyEmail = process.env.STUDIO_NOTIFY_EMAIL;

  if (notifyEmail) {
    await sendMail({
      to: notifyEmail.split(",").map((s) => s.trim()),
      subject: `Nowe zgłoszenie zapisu: ${childName} (${childAge} lat)`,
      text: `Nowe zgłoszenie z formularza "Zapisy" na stronie.\n\nDziecko: ${childName}\nWiek: ${childAge} lat\nE-mail rodzica: ${parentEmail}\nTelefon: ${parentPhone}\n\nOdpowiedz na ten e-mail lub zadzwoń, aby ustalić szczegóły zapisu.`,
      html: `<div style="font-family: sans-serif; max-width: 480px;">
        <p><strong>Nowe zgłoszenie z formularza "Zapisy" na stronie.</strong></p>
        <table style="border-collapse: collapse; margin: 16px 0;">
          <tr><td style="padding:4px 12px 4px 0; color:#666;">Dziecko</td><td>${escapeHtml(childName)}</td></tr>
          <tr><td style="padding:4px 12px 4px 0; color:#666;">Wiek</td><td>${childAge} lat</td></tr>
          <tr><td style="padding:4px 12px 4px 0; color:#666;">E-mail rodzica</td><td>${escapeHtml(parentEmail)}</td></tr>
          <tr><td style="padding:4px 12px 4px 0; color:#666;">Telefon</td><td>${escapeHtml(parentPhone)}</td></tr>
        </table>
      </div>`,
    });
  }

  redirect("/zapisy?success=1");
}

function escapeHtml(input: string): string {
  return input.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}
