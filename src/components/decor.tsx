/**
 * Small hand-drawn-feel decorative graphics used by the functional
 * account/admin chrome (navbar, footer, custom pages) — all inline SVG, no
 * external image assets. The public marketing pages use the studio's own
 * mockup screenshots instead (see components/mockup-frame.tsx).
 */

export function HeartDoodle({
  className,
  color = "var(--coral)",
}: {
  className?: string;
  color?: string;
}) {
  return (
    <svg viewBox="0 0 32 28" className={className} aria-hidden focusable="false">
      <path
        d="M16 26 C4 18 0 12 0 7.5 C0 2.5 4 0 8 0 C11.5 0 14.5 2 16 5 C17.5 2 20.5 0 24 0 C28 0 32 2.5 32 7.5 C32 12 28 18 16 26 Z"
        fill={color}
      />
    </svg>
  );
}

export function PaperPlaneDoodle({
  className,
  color = "var(--ink)",
}: {
  className?: string;
  color?: string;
}) {
  return (
    <svg viewBox="0 0 40 40" className={className} aria-hidden focusable="false" fill="none">
      <path
        d="M37 4 3 17l13 5m21-18-9 30-8-11m17-19L16 22"
        stroke={color}
        strokeWidth="2.2"
        strokeLinejoin="round"
        strokeLinecap="round"
      />
    </svg>
  );
}

export function DashedDivider({ className }: { className?: string }) {
  return (
    <div
      className={className}
      style={{
        borderTop: "2px dashed var(--border)",
      }}
      aria-hidden
    />
  );
}
