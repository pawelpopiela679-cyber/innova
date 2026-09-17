"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

type PillLink = { href: string; label: string; color: string };

export function NavPills({ links, className }: { links: PillLink[]; className?: string }) {
  const pathname = usePathname();

  return (
    <nav className={className}>
      {links.map((link) => {
        const active =
          link.href === "/" ? pathname === "/" : pathname.startsWith(link.href);
        return (
          <Link
            key={link.href}
            href={link.href}
            className={`inline-block rounded-full px-4 py-1.5 text-sm font-semibold text-[var(--ink)] transition-transform hover:-translate-y-0.5 hover:shadow-sm ${
              active ? "washi-underline shadow-sm" : ""
            }`}
            style={{ backgroundColor: link.color }}
          >
            {link.label}
          </Link>
        );
      })}
    </nav>
  );
}
