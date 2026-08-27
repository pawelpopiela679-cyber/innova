import { redirect } from "next/navigation";
import { getSession } from "@/lib/auth";
import { getTheme } from "@/lib/theme";
import { updateThemeAction, resetThemeAction } from "@/lib/actions/theme-actions";

const FIELDS: { key: keyof Awaited<ReturnType<typeof getTheme>>; label: string; hint: string }[] = [
  { key: "background", label: "Tło strony", hint: "np. beżowe tło pod wszystkim" },
  { key: "surface", label: "Tło kart i paneli", hint: "trochę jaśniejsze/ciemniejsze od tła" },
  { key: "border", label: "Obramowania", hint: "cienkie linie wokół kart, pól" },
  { key: "foreground", label: "Kolor tekstu", hint: "główny tekst na stronie" },
  { key: "muted", label: "Tekst przygaszony", hint: "opisy, daty, drobne informacje" },
  { key: "primary", label: "Kolor główny", hint: "przyciski, linki, akcenty" },
  { key: "primaryLight", label: "Jaśniejszy odcień głównego", hint: "dekoracyjne tło, plamy" },
  { key: "accent", label: "Akcent (np. róż)", hint: "wyróżnienia, drugi kolor marki" },
  { key: "gold", label: "Akcent złoty/beżowy", hint: "trzeci kolor marki" },
];

export default async function ThemeSettingsPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string; saved?: string; reset?: string }>;
}) {
  const sp = await searchParams;
  const session = await getSession();
  if (!session || session.role !== "ADMIN") {
    redirect("/admin");
  }

  const theme = await getTheme();

  return (
    <div className="max-w-2xl">
      <h1 className="text-2xl font-extrabold">Wygląd strony</h1>
      <p className="mt-1 text-[var(--muted)]">
        Zmień kolory strony bez potrzeby edycji kodu — zmiany widać od razu wszędzie, po
        zapisaniu.
      </p>

      {sp.error && (
        <p className="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{sp.error}</p>
      )}
      {sp.saved && (
        <p className="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
          Kolory zostały zapisane.
        </p>
      )}
      {sp.reset && (
        <p className="mt-4 rounded-lg bg-gray-100 px-4 py-2 text-sm text-gray-700">
          Przywrócono domyślną kolorystykę.
        </p>
      )}

      <form action={updateThemeAction} className="mt-6 space-y-3">
        {FIELDS.map((f) => (
          <div
            key={f.key}
            className="flex items-center justify-between gap-4 rounded-xl border border-[var(--border)] bg-[var(--surface)] p-4"
          >
            <div>
              <label htmlFor={f.key} className="font-medium">
                {f.label}
              </label>
              <p className="text-xs text-[var(--muted)]">{f.hint}</p>
            </div>
            <input
              id={f.key}
              name={f.key}
              type="color"
              defaultValue={theme[f.key]}
              className="h-10 w-16 cursor-pointer rounded border border-[var(--border)] bg-transparent"
            />
          </div>
        ))}

        <div className="flex gap-3 pt-2">
          <button
            type="submit"
            className="rounded-full bg-[var(--sage)] px-5 py-2.5 font-semibold text-white hover:opacity-90"
          >
            Zapisz kolory
          </button>
        </div>
      </form>

      <form action={resetThemeAction} className="mt-3">
        <button
          type="submit"
          className="rounded-full border border-[var(--border)] px-5 py-2.5 text-sm hover:bg-[var(--surface)]"
        >
          Przywróć domyślną kolorystykę
        </button>
      </form>
    </div>
  );
}
