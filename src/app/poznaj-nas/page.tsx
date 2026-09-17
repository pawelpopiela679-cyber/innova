import { getSession } from "@/lib/auth";
import { MockupFrame } from "@/components/mockup-frame";
import { MockupNav } from "@/components/mockup-nav";
import { MockupFooterLinks } from "@/components/mockup-footer-links";
import Link from "next/link";

export default async function AboutUsPage() {
  const session = await getSession();
  const isStaff = session?.role === "ADMIN" || session?.role === "INSTRUCTOR";

  return (
    <MockupFrame src="/mockups/o-nas.png" alt="INNOVA — O nas">
      <MockupNav isLoggedIn={!!session} isStaff={isStaff} />

      <Link
        href="#zespol"
        aria-label="Poznaj zespół"
        className="absolute"
        style={{ left: "59%", top: "35%", width: "17%", height: "9%" }}
      />
      <span id="zespol" className="absolute" style={{ top: "73%" }} />

      <MockupFooterLinks />
    </MockupFrame>
  );
}
