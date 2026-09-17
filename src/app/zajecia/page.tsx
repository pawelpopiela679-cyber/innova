import { prisma } from "@/lib/db";
import { getSession } from "@/lib/auth";
import { MockupFrame } from "@/components/mockup-frame";
import { MockupNav } from "@/components/mockup-nav";
import { MockupFooterLinks } from "@/components/mockup-footer-links";
import Link from "next/link";

const BUTTON_SPOTS: { key: string; left: number; top: number }[] = [
  { key: "CREATIVE", left: 8, top: 54.5 },
  { key: "ENGLISH", left: 39, top: 54.5 },
  { key: "THEATER", left: 70, top: 54.5 },
  { key: "ROBOTICS", left: 8, top: 85 },
  { key: "MATH", left: 39, top: 85 },
  { key: "SCIENCE", left: 70, top: 85 },
];

export default async function ClassesPage() {
  const [session, classTypes] = await Promise.all([
    getSession(),
    prisma.classType.findMany({ select: { id: true, key: true } }),
  ]);
  const isStaff = session?.role === "ADMIN" || session?.role === "INSTRUCTOR";
  const idByKey = Object.fromEntries(classTypes.map((c) => [c.key, c.id]));

  return (
    <MockupFrame src="/mockups/zajecia.png" alt="INNOVA — Zajęcia">
      <MockupNav isLoggedIn={!!session} isStaff={isStaff} />

      {BUTTON_SPOTS.map((b) => {
        const id = idByKey[b.key];
        if (!id) return null;
        return (
          <Link
            key={b.key}
            href={`/kalendarz?classType=${id}`}
            className="absolute"
            style={{ left: `${b.left}%`, top: `${b.top}%`, width: "19%", height: "6.5%" }}
          />
        );
      })}

      <MockupFooterLinks />
    </MockupFrame>
  );
}
