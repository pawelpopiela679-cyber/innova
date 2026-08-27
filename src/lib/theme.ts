import { prisma } from "@/lib/db";

export const DEFAULT_THEME = {
  background: "#efe4cf",
  foreground: "#4a4326",
  surface: "#f8f3e6",
  border: "#e2d3ac",
  primary: "#7d7a4a",
  primaryLight: "#b3af86",
  accent: "#c9848a",
  gold: "#c2a05e",
  muted: "#8a7f5c",
};

export type Theme = typeof DEFAULT_THEME;

/** Current theme colors — DB row if the master admin has customized them, otherwise the built-in defaults. */
export async function getTheme(): Promise<Theme> {
  const row = await prisma.siteSettings.findUnique({ where: { id: "singleton" } });
  if (!row) return DEFAULT_THEME;
  return {
    background: row.background,
    foreground: row.foreground,
    surface: row.surface,
    border: row.border,
    primary: row.primary,
    primaryLight: row.primaryLight,
    accent: row.accent,
    gold: row.gold,
    muted: row.muted,
  };
}
