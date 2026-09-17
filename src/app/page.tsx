import Link from "next/link";
import { getSession } from "@/lib/auth";
import { MockupFrame } from "@/components/mockup-frame";
import { MockupNav } from "@/components/mockup-nav";
import { MockupFooterLinks } from "@/components/mockup-footer-links";

export default async function HomePage() {
  const session = await getSession();
  const isStaff = session?.role === "ADMIN" || session?.role === "INSTRUCTOR";

  return (
    <MockupFrame src="/mockups/home.png" alt="INNOVA — Miejsce, w którym pomysły rosną">
      <MockupNav isLoggedIn={!!session} isStaff={isStaff} />

      <Link
        href="/poznaj-nas"
        aria-label="Poznaj nas bliżej"
        className="absolute"
        style={{ left: "43.5%", top: "47%", width: "16.5%", height: "8.5%" }}
      />
      <Link
        href="/zajecia"
        aria-label="Zobacz zajęcia"
        className="absolute"
        style={{ left: "63%", top: "47%", width: "15%", height: "8.5%" }}
      />

      {[
        { href: "/aktualnosci", left: 1 },
        { href: "/poznaj-nas", left: 17.2 },
        { href: "/zajecia", left: 33.2 },
        { href: "/kalendarz", left: 49.2 },
        { href: "/zapisy", left: 65.2 },
        { href: "/kontakt", left: 81.2 },
      ].map((c) => (
        <Link
          key={c.href}
          href={c.href}
          className="absolute"
          style={{ left: `${c.left}%`, top: "61%", width: "14.3%", height: "33%" }}
        />
      ))}
      <MockupFooterLinks />
    </MockupFrame>
  );
}
