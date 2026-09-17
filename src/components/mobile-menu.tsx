"use client";

import { useState } from "react";

export function MobileMenu({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useState(false);

  return (
    <div className="lg:hidden">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-label="Menu"
        className="flex h-9 w-9 flex-col items-center justify-center gap-1.5 rounded-full border border-[var(--border)] bg-[var(--surface)]"
      >
        <span className="h-0.5 w-4 rounded bg-[var(--ink)]" />
        <span className="h-0.5 w-4 rounded bg-[var(--ink)]" />
        <span className="h-0.5 w-4 rounded bg-[var(--ink)]" />
      </button>

      {open && (
        <div className="absolute inset-x-0 top-full z-20 border-b border-[var(--border)] bg-[var(--surface)] px-4 py-4 shadow-lg">
          {children}
        </div>
      )}
    </div>
  );
}
