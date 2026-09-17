/**
 * Shared "scrapbook" chrome — spiral-bound notebook cards and sticky
 * notes — used across the marketing pages to match the studio's
 * hand-drawn promotional materials. Pure presentation, no data fetching.
 */

type Rotation = "-rotate-1" | "rotate-1" | "-rotate-2" | "rotate-2" | "rotate-0";

export function NotebookCard({
  children,
  tab,
  className,
  rotate = "rotate-0",
  id,
}: {
  children: React.ReactNode;
  tab?: { label: string; color: string };
  className?: string;
  rotate?: Rotation;
  id?: string;
}) {
  return (
    <div id={id} className={`notebook ${rotate} p-5 sm:p-6 ${className ?? ""}`}>
      {tab && (
        <span className="notebook-tab" style={{ backgroundColor: tab.color }}>
          {tab.label}
        </span>
      )}
      {children}
    </div>
  );
}

const NOTE_COLORS: Record<string, string> = {
  pink: "var(--pill-pink)",
  green: "var(--pill-mint)",
  yellow: "var(--mustard-soft)",
  purple: "var(--pill-purple)",
  blue: "var(--pill-blue)",
};

export function StickyNote({
  children,
  color = "pink",
  rotate = "-rotate-2",
  className,
}: {
  children: React.ReactNode;
  color?: keyof typeof NOTE_COLORS;
  rotate?: string;
  className?: string;
}) {
  return (
    <div
      className={`sticky-note ${rotate} px-4 py-3 text-center text-sm font-semibold ${className ?? ""}`}
      style={{ backgroundColor: NOTE_COLORS[color] }}
    >
      {children}
    </div>
  );
}

export function SectionHeading({
  eyebrow,
  title,
  subtitle,
  highlight,
}: {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  highlight?: string;
}) {
  return (
    <div className="relative text-center">
      {eyebrow && (
        <p className="font-script text-2xl text-[var(--sage)] sm:text-3xl">{eyebrow}</p>
      )}
      <h1 className="relative mt-1 inline-block font-heading text-4xl font-extrabold text-[var(--ink)] sm:text-5xl">
        <span
          className="absolute inset-x-[-6%] bottom-1 -z-10 h-[0.5em] rounded-full opacity-50"
          style={{ backgroundColor: highlight ?? "var(--pill-blue)" }}
          aria-hidden
        />
        {title}
      </h1>
      {subtitle && (
        <p className="mx-auto mt-3 max-w-xl text-[var(--muted)]">{subtitle}</p>
      )}
    </div>
  );
}
