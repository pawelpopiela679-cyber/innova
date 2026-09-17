import { getSession } from "@/lib/auth";
import { MockupFrame } from "@/components/mockup-frame";
import { MockupNav } from "@/components/mockup-nav";
import { MockupFooterLinks } from "@/components/mockup-footer-links";
import Link from "next/link";

export default async function NewsPage() {
  const session = await getSession();
  const isStaff = session?.role === "ADMIN" || session?.role === "INSTRUCTOR";

  return (
    <MockupFrame src="/mockups/aktualnosci.png" alt="INNOVA — Aktualności">
      <MockupNav isLoggedIn={!!session} isStaff={isStaff} />

      <Link
        href="/zapisy"
        aria-label="Zapisz dziecko"
        className="absolute"
        style={{ left: "78%", top: "50%", width: "12%", height: "7%" }}
      />

      <MockupFooterLinks />
    </MockupFrame>
  );
}
