/**
 * Small hand-drawn-feel decorative graphics (blobs, leaf sprigs, sparkles)
 * used to give pages the same illustrated, friendly feel as the studio's
 * flyer — all inline SVG, no external image assets or network requests.
 */

export function Blob({
  className,
  color = "var(--sage-light)",
}: {
  className?: string;
  color?: string;
}) {
  return (
    <svg
      viewBox="0 0 200 200"
      className={className}
      aria-hidden
      focusable="false"
    >
      <path
        fill={color}
        d="M45.1,-58.6C57.9,-49.6,67.2,-34.6,71.4,-18.2C75.6,-1.8,74.7,16,67.4,30.7C60.1,45.4,46.5,57,31,63.8C15.6,70.6,-1.7,72.7,-18.4,69.1C-35.1,65.5,-51.2,56.2,-61.6,42.4C-72,28.6,-76.7,10.3,-74.5,-6.9C-72.3,-24.1,-63.2,-40.2,-50.1,-49.6C-37,-59,-18.5,-61.7,-0.6,-61C17.3,-60.3,32.3,-67.6,45.1,-58.6Z"
        transform="translate(100 100)"
      />
    </svg>
  );
}

export function LeafSprig({
  className,
  color = "var(--sage)",
}: {
  className?: string;
  color?: string;
}) {
  return (
    <svg viewBox="0 0 60 140" className={className} aria-hidden focusable="false">
      <path
        d="M30 135 C30 100 30 60 30 8"
        fill="none"
        stroke={color}
        strokeWidth="3"
        strokeLinecap="round"
      />
      {[24, 46, 68, 90].map((y, i) => (
        <path
          key={y}
          d={
            i % 2 === 0
              ? `M30 ${y} C10 ${y - 10} 2 ${y - 26} 10 ${y - 40}`
              : `M30 ${y} C50 ${y - 10} 58 ${y - 26} 50 ${y - 40}`
          }
          fill="none"
          stroke={color}
          strokeWidth="3"
          strokeLinecap="round"
          opacity={0.85 - i * 0.12}
        />
      ))}
    </svg>
  );
}

export function Sparkle({
  className,
  color = "var(--mustard)",
}: {
  className?: string;
  color?: string;
}) {
  return (
    <svg viewBox="0 0 40 40" className={className} aria-hidden focusable="false">
      <path
        d="M20 2 C21 13 21 19 32 20 C21 21 21 27 20 38 C19 27 19 21 8 20 C19 19 19 13 20 2 Z"
        fill={color}
      />
    </svg>
  );
}

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

/** Friendly flat-vector "shelf" illustration for the hero — books, a plant,
 * and a little rainbow, echoing the flyer's photo without needing an image
 * asset. */
export function HeroIllustration({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 360 320" className={className} aria-hidden focusable="false">
      {/* soft background blob */}
      <path
        fill="var(--sage-light)"
        opacity="0.35"
        d="M180 20c62 0 120 38 132 96 11 55-24 108-84 128-63 21-140 6-168-46C32 148 46 82 96 46 122 27 148 20 180 20Z"
      />

      {/* shelf */}
      <rect x="40" y="230" width="280" height="14" rx="7" fill="var(--sage)" />
      <rect x="55" y="244" width="14" height="46" rx="4" fill="var(--sage)" />
      <rect x="291" y="244" width="14" height="46" rx="4" fill="var(--sage)" />

      {/* books */}
      <rect x="66" y="168" width="26" height="62" rx="4" fill="var(--coral)" />
      <rect x="94" y="150" width="26" height="80" rx="4" fill="var(--mustard)" />
      <rect x="122" y="180" width="24" height="50" rx="4" fill="var(--sage)" />

      {/* rainbow */}
      <path d="M180 200a56 56 0 0 1 112 0" fill="none" stroke="var(--coral)" strokeWidth="10" strokeLinecap="round" />
      <path d="M192 200a44 44 0 0 1 88 0" fill="none" stroke="var(--mustard)" strokeWidth="10" strokeLinecap="round" />
      <path d="M204 200a32 32 0 0 1 64 0" fill="none" stroke="var(--sage)" strokeWidth="10" strokeLinecap="round" />

      {/* plant pot */}
      <path d="M296 200 l8 30 h-30 l8 -30 Z" fill="var(--coral)" />
      <path
        d="M300 200 C295 180 285 178 282 190 M300 200 C305 178 315 178 316 192 M300 200 C296 172 300 165 300 165"
        fill="none"
        stroke="var(--sage)"
        strokeWidth="4"
        strokeLinecap="round"
      />

      {/* little star accents */}
      <path
        d="M60 90 C61 98 61 102 69 103 C61 104 61 108 60 116 C59 108 59 104 51 103 C59 102 59 98 60 90 Z"
        fill="var(--mustard)"
      />
      <path
        d="M320 120 C321 126 321 129 327 130 C321 131 321 134 320 140 C319 134 319 131 313 130 C319 129 319 126 320 120 Z"
        fill="var(--coral)"
      />
    </svg>
  );
}
