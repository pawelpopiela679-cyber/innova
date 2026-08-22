/** Small decorative icon per class type key, matching the studio's flyer. */
export const CLASS_TYPE_ICONS: Record<string, string> = {
  ENGLISH: "🔤",
  THEATER: "🎭",
  ROBOTICS: "🤖",
  CREATIVE: "🧶",
  MATH: "🧮",
  SCIENCE: "🧪",
};

export function classTypeIcon(key: string): string {
  return CLASS_TYPE_ICONS[key] ?? "⭐";
}
