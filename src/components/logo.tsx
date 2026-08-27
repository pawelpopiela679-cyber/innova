/**
 * The studio's wordmark: "IN" (sage) + "N" (coral) + a hollow/outlined "O"
 * (coral) + "VA" (mustard), optionally with the "Pracownia
 * kreatywno-edukacyjna" subtitle flanked by short dashes — matching the
 * studio's actual logo.
 */
const SIZES = {
  sm: { text: "text-2xl", hollowWidth: "1.3px" },
  md: { text: "text-3xl sm:text-4xl", hollowWidth: "1.8px" },
  lg: { text: "text-5xl sm:text-6xl", hollowWidth: "2.6px" },
} as const;

export function Logo({
  size = "md",
  withSubtitle = false,
  align = "center",
  className,
}: {
  size?: keyof typeof SIZES;
  withSubtitle?: boolean;
  align?: "center" | "start";
  className?: string;
}) {
  const { text, hollowWidth } = SIZES[size];

  return (
    <span
      className={`inline-flex flex-col ${align === "center" ? "items-center" : "items-start"} ${className ?? ""}`}
    >
      <span className={`font-logo font-bold ${text}`} style={{ letterSpacing: "0.01em" }}>
        <span style={{ color: "var(--logo-green)" }}>IN</span>
        <span style={{ color: "var(--logo-pink)" }}>N</span>
        <span
          className="hollow-text"
          style={
            {
              "--hollow-color": "var(--logo-pink)",
              "--hollow-width": hollowWidth,
            } as React.CSSProperties
          }
        >
          O
        </span>
        <span style={{ color: "var(--logo-gold)" }}>VA</span>
      </span>

      {withSubtitle && (
        <span className="mt-1.5 flex items-center gap-2 text-xs font-medium text-[var(--muted)] sm:text-sm">
          <span className="h-px w-5 bg-[var(--muted)] opacity-50" aria-hidden />
          Pracownia kreatywno-edukacyjna
          <span className="h-px w-5 bg-[var(--muted)] opacity-50" aria-hidden />
        </span>
      )}
    </span>
  );
}
