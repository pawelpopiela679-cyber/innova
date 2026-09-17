import { getSession } from "@/lib/auth";
import { MockupFrame } from "@/components/mockup-frame";
import { MockupNav } from "@/components/mockup-nav";
import { MockupFooterLinks } from "@/components/mockup-footer-links";
import { submitContactAction } from "@/lib/actions/contact-actions";

const fieldClass =
  "absolute rounded-md bg-transparent px-2 text-[var(--ink)] outline-none focus:bg-white/40";

export default async function ContactPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string; success?: string }>;
}) {
  const sp = await searchParams;
  const session = await getSession();
  const isStaff = session?.role === "ADMIN" || session?.role === "INSTRUCTOR";

  return (
    <MockupFrame src="/mockups/kontakt.png" alt="INNOVA — Kontakt">
      <MockupNav isLoggedIn={!!session} isStaff={isStaff} />

      {sp.success ? (
        <div
          className="absolute flex flex-col items-center justify-center rounded-xl bg-[var(--paper)]/95 p-4 text-center shadow-inner"
          style={{ left: "11.5%", top: "35%", width: "26%", height: "50%" }}
        >
          <p className="text-3xl">💌</p>
          <p className="mt-2 font-heading text-base font-bold text-[var(--sage)] sm:text-lg">
            Wiadomość wysłana!
          </p>
          <p className="mt-2 text-xs text-[var(--muted)] sm:text-sm">
            Odpowiemy najszybciej, jak to możliwe.
          </p>
        </div>
      ) : (
        <form action={submitContactAction}>
          {sp.error && (
            <p
              className="absolute rounded-lg bg-red-50 px-3 py-1.5 text-[10px] text-red-700 sm:text-xs"
              style={{ left: "13%", top: "36%", width: "22%" }}
            >
              {sp.error}
            </p>
          )}
          <input
            aria-label="Imię i nazwisko"
            name="name"
            required
            className={fieldClass}
            style={{ left: "13%", top: "43%", width: "22.3%", height: "3.6%" }}
          />
          <input
            aria-label="Adres e-mail"
            name="email"
            type="email"
            required
            className={fieldClass}
            style={{ left: "13%", top: "48.5%", width: "22.3%", height: "3.6%" }}
          />
          <input
            aria-label="Temat"
            name="subject"
            className={fieldClass}
            style={{ left: "13%", top: "54%", width: "22.3%", height: "3.6%" }}
          />
          <textarea
            aria-label="Wiadomość"
            name="message"
            required
            className={`${fieldClass} resize-none py-1.5`}
            style={{ left: "13%", top: "59.3%", width: "22.3%", height: "12.7%" }}
          />
          <input
            aria-label="Wyrażam zgodę na przetwarzanie moich danych osobowych"
            name="consent"
            type="checkbox"
            required
            className="absolute cursor-pointer accent-[var(--ink)]"
            style={{ left: "14%", top: "75%", width: "1.3%", height: "2.3%" }}
          />
          <button
            type="submit"
            aria-label="Wyślij wiadomość"
            className="absolute"
            style={{ left: "13.5%", top: "78%", width: "21.5%", height: "6.5%" }}
          />
        </form>
      )}

      <a
        href="mailto:biuro@innova-pracownia.pl"
        aria-label="Napisz e-mail: biuro@innova-pracownia.pl"
        className="absolute"
        style={{ left: "43%", top: "50.5%", width: "15%", height: "4%" }}
      />
      <a
        href="tel:+48123456789"
        aria-label="Zadzwoń: +48 123 456 789"
        className="absolute"
        style={{ left: "43%", top: "59.5%", width: "15%", height: "4.5%" }}
      />
      <a
        href="https://facebook.com/innova.pracownia"
        aria-label="Facebook"
        className="absolute"
        style={{ left: "45.4%", top: "71.5%", width: "3%", height: "5.5%" }}
      />
      <a
        href="https://instagram.com/innova_pracownia"
        aria-label="Instagram"
        className="absolute"
        style={{ left: "50.6%", top: "71.5%", width: "3%", height: "5.5%" }}
      />

      <MockupFooterLinks />
    </MockupFrame>
  );
}
