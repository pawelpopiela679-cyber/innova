import { getSession } from "@/lib/auth";
import { MockupFrame } from "@/components/mockup-frame";
import { MockupNav } from "@/components/mockup-nav";
import { MockupFooterLinks } from "@/components/mockup-footer-links";
import { submitLeadAction } from "@/lib/actions/lead-actions";
import Link from "next/link";

const fieldClass =
  "absolute rounded-md bg-transparent px-2 text-[var(--ink)] outline-none focus:bg-white/40";

export default async function SignupsPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string; success?: string }>;
}) {
  const sp = await searchParams;
  const session = await getSession();
  const isStaff = session?.role === "ADMIN" || session?.role === "INSTRUCTOR";

  return (
    <MockupFrame src="/mockups/zapisy.png" alt="INNOVA — Zapisy">
      <MockupNav isLoggedIn={!!session} isStaff={isStaff} />

      <Link
        href="/kontakt"
        aria-label="Przejdź do kontaktu"
        className="absolute"
        style={{ left: "70%", top: "84.5%", width: "14%", height: "6%" }}
      />

      {sp.success ? (
        <div
          className="absolute flex flex-col items-center justify-center rounded-xl bg-[var(--paper)]/95 p-4 text-center shadow-inner"
          style={{ left: "54.5%", top: "31%", width: "33.5%", height: "46%" }}
        >
          <p className="text-3xl">🎉</p>
          <p className="mt-2 font-heading text-base font-bold text-[var(--sage)] sm:text-lg">
            Dziękujemy za zgłoszenie!
          </p>
          <p className="mt-2 text-xs text-[var(--muted)] sm:text-sm">
            Skontaktujemy się wkrótce, aby potwierdzić zapis i dobrać odpowiednią grupę.
          </p>
        </div>
      ) : (
        <form action={submitLeadAction}>
          {sp.error && (
            <p
              className="absolute rounded-lg bg-red-50 px-3 py-1.5 text-[10px] text-red-700 sm:text-xs"
              style={{ left: "58.7%", top: "31.5%", width: "29%" }}
            >
              {sp.error}
            </p>
          )}
          <input
            aria-label="Imię dziecka"
            name="childName"
            required
            className={fieldClass}
            style={{ left: "58.7%", top: "42%", width: "17%", height: "4.7%" }}
          />
          <input
            aria-label="Wiek dziecka"
            name="childAge"
            type="number"
            min={1}
            max={18}
            required
            className={fieldClass}
            style={{ left: "78.5%", top: "42%", width: "9.3%", height: "4.7%" }}
          />
          <input
            aria-label="Twój adres e-mail"
            name="parentEmail"
            type="email"
            required
            className={fieldClass}
            style={{ left: "58.7%", top: "49.5%", width: "29%", height: "4.7%" }}
          />
          <input
            aria-label="Numer telefonu"
            name="parentPhone"
            required
            className={fieldClass}
            style={{ left: "58.7%", top: "57%", width: "29%", height: "4.7%" }}
          />
          <input
            aria-label="Wyrażam zgodę na kontakt w sprawie zapisów i organizacji zajęć"
            name="consent"
            type="checkbox"
            required
            className="absolute cursor-pointer accent-[var(--ink)]"
            style={{ left: "58.7%", top: "63.5%", width: "1.3%", height: "2.3%" }}
          />
          <button
            type="submit"
            aria-label="Wyślij zgłoszenie"
            className="absolute"
            style={{ left: "59%", top: "68%", width: "25%", height: "5.5%" }}
          />
        </form>
      )}

      <MockupFooterLinks />
    </MockupFrame>
  );
}
