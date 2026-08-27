import nodemailer from "nodemailer";
import { format } from "date-fns";
import { pl } from "date-fns/locale";
import { calculateAge } from "@/lib/age";

type MailInput = {
  to: string | string[];
  subject: string;
  html: string;
  text: string;
};

let cachedTransporter: nodemailer.Transporter | null | undefined;

/**
 * Returns a configured SMTP transporter, or `null` when no SMTP host is
 * configured (local/dev). Callers should fall back to logging in that case.
 */
function getTransporter(): nodemailer.Transporter | null {
  if (cachedTransporter !== undefined) return cachedTransporter;

  const host = process.env.SMTP_HOST;
  if (!host) {
    cachedTransporter = null;
    return null;
  }

  cachedTransporter = nodemailer.createTransport({
    host,
    port: Number(process.env.SMTP_PORT ?? 587),
    secure: Number(process.env.SMTP_PORT ?? 587) === 465,
    auth: process.env.SMTP_USER
      ? { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS }
      : undefined,
  });
  return cachedTransporter;
}

/** Sends an email, or logs it to the console when SMTP isn't configured. */
export async function sendMail(input: MailInput): Promise<void> {
  const transporter = getTransporter();
  const from = process.env.SMTP_FROM || "INNOVA <no-reply@innova-pracownia.local>";

  if (!transporter) {
    // Dev fallback: nothing is actually sent, but the content is visible
    // in the server log so the flow can be tested without SMTP creds.
    console.log(
      `\n[DEV MAIL] (SMTP not configured, not sent)\nTo: ${
        Array.isArray(input.to) ? input.to.join(", ") : input.to
      }\nSubject: ${input.subject}\n---\n${input.text}\n---\n`
    );
    return;
  }

  await transporter.sendMail({
    from,
    to: input.to,
    subject: input.subject,
    html: input.html,
    text: input.text,
  });
}

function formatSessionDate(date: Date): string {
  return format(date, "EEEE d MMMM yyyy, HH:mm", { locale: pl });
}

export async function sendEnrollmentConfirmationEmail(params: {
  parentEmail: string;
  parentName: string;
  childName: string;
  classTypeName: string;
  sessionTitle: string;
  startsAt: Date;
  endsAt: Date;
  instructorName: string;
  meetingUrl?: string | null;
  waitlisted: boolean;
}) {
  const {
    parentEmail,
    parentName,
    childName,
    classTypeName,
    sessionTitle,
    startsAt,
    endsAt,
    instructorName,
    meetingUrl,
    waitlisted,
  } = params;

  const when = `${formatSessionDate(startsAt)} – ${format(endsAt, "HH:mm")}`;
  const statusLine = waitlisted
    ? "Grupa jest obecnie pełna — dziecko zostało zapisane na listę rezerwową. Odezwiemy się, jeśli zwolni się miejsce."
    : "Zapis został potwierdzony — miejsce jest zarezerwowane.";

  const subject = waitlisted
    ? `Lista rezerwowa: ${sessionTitle} (${childName})`
    : `Potwierdzenie zapisu: ${sessionTitle} (${childName})`;

  const text = `Cześć ${parentName},

${statusLine}

Zajęcia: ${classTypeName} — ${sessionTitle}
Dziecko: ${childName}
Termin: ${when}
Prowadzący: ${instructorName}
${meetingUrl && !waitlisted ? `Link do zajęć online: ${meetingUrl}\n` : ""}
Do zobaczenia na zajęciach!

INNOVA — Pracownia kreatywno-edukacyjna`;

  const html = `
    <div style="font-family: sans-serif; max-width: 480px;">
      <p>Cześć ${escapeHtml(parentName)},</p>
      <p>${escapeHtml(statusLine)}</p>
      <table style="border-collapse: collapse; margin: 16px 0;">
        <tr><td style="padding:4px 12px 4px 0; color:#666;">Zajęcia</td><td><strong>${escapeHtml(
          classTypeName
        )} — ${escapeHtml(sessionTitle)}</strong></td></tr>
        <tr><td style="padding:4px 12px 4px 0; color:#666;">Dziecko</td><td>${escapeHtml(
          childName
        )}</td></tr>
        <tr><td style="padding:4px 12px 4px 0; color:#666;">Termin</td><td>${escapeHtml(
          when
        )}</td></tr>
        <tr><td style="padding:4px 12px 4px 0; color:#666;">Prowadzący</td><td>${escapeHtml(
          instructorName
        )}</td></tr>
        ${
          meetingUrl && !waitlisted
            ? `<tr><td style="padding:4px 12px 4px 0; color:#666;">Link online</td><td><a href="${escapeHtml(
                meetingUrl
              )}">${escapeHtml(meetingUrl)}</a></td></tr>`
            : ""
        }
      </table>
      <p>Do zobaczenia na zajęciach!</p>
      <p style="color:#999; font-size: 12px;">INNOVA — Pracownia kreatywno-edukacyjna</p>
    </div>`;

  await sendMail({ to: parentEmail, subject, html, text });
}

/** Sent to the parent immediately after they submit a request — not a final confirmation. */
export async function sendEnrollmentPendingEmail(params: {
  parentEmail: string;
  parentName: string;
  childName: string;
  classTypeName: string;
  sessionTitle: string;
  startsAt: Date;
  endsAt: Date;
}) {
  const { parentEmail, parentName, childName, classTypeName, sessionTitle, startsAt, endsAt } =
    params;
  const when = `${formatSessionDate(startsAt)} – ${format(endsAt, "HH:mm")}`;
  const subject = `Zgłoszenie przyjęte: ${sessionTitle} (${childName})`;

  const text = `Cześć ${parentName},

Dziękujemy za zgłoszenie! Twoje zgłoszenie oczekuje na potwierdzenie przez pracownię —
sprawdzimy dostępność miejsc i dobierzemy właściwą grupę wiekową dla dziecka.
Gdy tylko to zrobimy, wyślemy kolejnego e-maila z ostatecznym potwierdzeniem i przypisaną grupą.

Zgłoszone zajęcia: ${classTypeName} — ${sessionTitle}
Dziecko: ${childName}
Wybrany termin: ${when}

INNOVA — Pracownia kreatywno-edukacyjna`;

  const html = `
    <div style="font-family: sans-serif; max-width: 480px;">
      <p>Cześć ${escapeHtml(parentName)},</p>
      <p>Dziękujemy za zgłoszenie! Twoje zgłoszenie oczekuje na potwierdzenie przez pracownię —
      sprawdzimy dostępność miejsc i dobierzemy właściwą grupę wiekową dla dziecka. Gdy tylko to
      zrobimy, wyślemy kolejnego e-maila z ostatecznym potwierdzeniem i przypisaną grupą.</p>
      <table style="border-collapse: collapse; margin: 16px 0;">
        <tr><td style="padding:4px 12px 4px 0; color:#666;">Zajęcia</td><td><strong>${escapeHtml(
          classTypeName
        )} — ${escapeHtml(sessionTitle)}</strong></td></tr>
        <tr><td style="padding:4px 12px 4px 0; color:#666;">Dziecko</td><td>${escapeHtml(
          childName
        )}</td></tr>
        <tr><td style="padding:4px 12px 4px 0; color:#666;">Wybrany termin</td><td>${escapeHtml(
          when
        )}</td></tr>
      </table>
      <p style="color:#999; font-size: 12px;">INNOVA — Pracownia kreatywno-edukacyjna</p>
    </div>`;

  await sendMail({ to: parentEmail, subject, html, text });
}

/** Sent to the parent if the studio declines/cancels a pending request. */
export async function sendEnrollmentDeclinedEmail(params: {
  parentEmail: string;
  parentName: string;
  childName: string;
  classTypeName: string;
  sessionTitle: string;
}) {
  const { parentEmail, parentName, childName, classTypeName, sessionTitle } = params;
  const subject = `Zgłoszenie nie zostało przyjęte: ${sessionTitle} (${childName})`;
  const text = `Cześć ${parentName},

Niestety nie możemy przyjąć zgłoszenia dziecka (${childName}) na zajęcia: ${classTypeName} — ${sessionTitle}.
Zachęcamy do sprawdzenia innych terminów w kalendarzu lub kontaktu z nami.

INNOVA — Pracownia kreatywno-edukacyjna`;
  const html = `
    <div style="font-family: sans-serif; max-width: 480px;">
      <p>Cześć ${escapeHtml(parentName)},</p>
      <p>Niestety nie możemy przyjąć zgłoszenia dziecka (${escapeHtml(
        childName
      )}) na zajęcia: <strong>${escapeHtml(classTypeName)} — ${escapeHtml(
        sessionTitle
      )}</strong>.</p>
      <p>Zachęcamy do sprawdzenia innych terminów w kalendarzu lub kontaktu z nami.</p>
      <p style="color:#999; font-size: 12px;">INNOVA — Pracownia kreatywno-edukacyjna</p>
    </div>`;
  await sendMail({ to: parentEmail, subject, html, text });
}

/** Notifies the studio of a new pending request that needs review + group assignment. */
export async function sendStudioNewSignupNotification(params: {
  childName: string;
  childBirthDate: Date;
  parentName: string;
  parentEmail: string;
  parentPhone?: string | null;
  classTypeName: string;
  sessionTitle: string;
  startsAt: Date;
  endsAt: Date;
  confirmedCount: number;
  capacity: number;
}) {
  const notifyEmail = process.env.STUDIO_NOTIFY_EMAIL;
  if (!notifyEmail) return;

  const {
    childName,
    childBirthDate,
    parentName,
    parentEmail,
    parentPhone,
    classTypeName,
    sessionTitle,
    startsAt,
    endsAt,
    confirmedCount,
    capacity,
  } = params;

  const age = calculateAge(childBirthDate);
  const when = `${formatSessionDate(startsAt)} – ${format(endsAt, "HH:mm")}`;
  const subject = `Nowe zgłoszenie — wymaga potwierdzenia: ${childName} (${age} lat) → ${sessionTitle}`;

  const text = `Nowe zgłoszenie na zajęcia — wymaga potwierdzenia i przypisania grupy!

Zajęcia: ${classTypeName} — ${sessionTitle}
Wybrany termin: ${when}
Zajętość wybranej grupy: ${confirmedCount}/${capacity}

Dziecko: ${childName} — WIEK: ${age} lat (ur. ${format(childBirthDate, "d MMMM yyyy", { locale: pl })})
Rodzic/opiekun: ${parentName}
E-mail: ${parentEmail}
Telefon: ${parentPhone ?? "brak"}

Potwierdź zgłoszenie i przypisz grupę w panelu: /admin/zapisy`;

  const html = `
    <div style="font-family: sans-serif; max-width: 480px;">
      <p><strong>Nowe zgłoszenie na zajęcia — wymaga potwierdzenia i przypisania grupy!</strong></p>
      <p>${escapeHtml(classTypeName)} — ${escapeHtml(sessionTitle)}<br/>
      ${escapeHtml(when)}<br/>
      Zajętość wybranej grupy: ${confirmedCount}/${capacity}</p>
      <p>Dziecko: ${escapeHtml(childName)} — <strong>wiek: ${age} lat</strong> (ur. ${format(
        childBirthDate,
        "d MMMM yyyy",
        { locale: pl }
      )})<br/>
      Rodzic/opiekun: ${escapeHtml(parentName)}<br/>
      E-mail: ${escapeHtml(parentEmail)}<br/>
      Telefon: ${escapeHtml(parentPhone ?? "brak")}</p>
      <p><a href="/admin/zapisy">Potwierdź zgłoszenie i przypisz grupę →</a></p>
    </div>`;

  await sendMail({ to: notifyEmail.split(",").map((s) => s.trim()), subject, html, text });
}

function escapeHtml(input: string): string {
  return input
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}
