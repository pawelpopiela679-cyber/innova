import Link from "next/link";
import { NAV_LINKS } from "@/components/nav-links";

const PILL_X: Record<string, { left: number; width: number }> = {
  "/": { left: 32.2, width: 9.9 },
  "/aktualnosci": { left: 42.9, width: 9.7 },
  "/poznaj-nas": { left: 53.3, width: 6.5 },
  "/zajecia": { left: 59.9, width: 8.3 },
  "/kalendarz": { left: 68.8, width: 7.4 },
  "/zapisy": { left: 76.3, width: 6.9 },
  "/kontakt": { left: 83.8, width: 8.6 },
};

/**
 * Invisible click targets over the nav pills baked into every mockup
 * screenshot (all seven share the same header). Also exposes a small,
 * plain text auth strip — the mockups don't design one, so this stays
 * deliberately minimal instead of inventing new visual chrome.
 */
export function MockupNav({
  isLoggedIn,
  isStaff,
}: {
  isLoggedIn: boolean;
  isStaff: boolean;
}) {
  return (
    <>
      <Link
        href="/"
        aria-label="Strona główna INNOVA"
        className="absolute"
        style={{ left: "0.8%", top: "0.5%", width: "29%", height: "9%" }}
      />
      {NAV_LINKS.map((l) => {
        const pos = PILL_X[l.href];
        if (!pos) return null;
        return (
          <Link
            key={l.href}
            href={l.href}
            title={l.label}
            className="absolute"
            style={{ left: `${pos.left}%`, top: "3%", width: `${pos.width}%`, height: "6%" }}
          />
        );
      })}

      <div
        className="absolute flex items-center gap-3 text-[11px] font-semibold text-[var(--ink)] sm:text-xs"
        style={{ left: "0.8%", top: "10.5%" }}
      >
        {isLoggedIn ? (
          <>
            <Link href={isStaff ? "/admin" : "/panel"} className="underline decoration-dotted hover:text-[var(--coral)]">
              {isStaff ? "Panel prowadzącego" : "Panel rodzica"}
            </Link>
          </>
        ) : (
          <>
            <Link href="/logowanie" className="underline decoration-dotted hover:text-[var(--coral)]">
              Zaloguj się
            </Link>
            <Link href="/rejestracja" className="underline decoration-dotted hover:text-[var(--coral)]">
              Załóż konto
            </Link>
          </>
        )}
      </div>
    </>
  );
}
