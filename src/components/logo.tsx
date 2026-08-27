"use client";

import { useEffect, useRef, useState } from "react";

/**
 * The studio's wordmark. Prefers the real logo file at `/public/logo.png`
 * (drop the exact original file there — see README) so the site shows the
 * literal brand asset, not a recreation. If that file is missing (404), it
 * falls back to a coded approximation: "IN" (green) + "N" (pink) + a
 * hollow/outlined "O" (pink) + "VA" (gold), matching the studio's logo as
 * closely as achievable without the source file.
 */
const SIZES = {
  sm: { text: "text-2xl", hollowWidth: "1.3px", imgHeight: "h-9" },
  md: { text: "text-3xl sm:text-4xl", hollowWidth: "1.8px", imgHeight: "h-14 sm:h-16" },
  lg: { text: "text-5xl sm:text-6xl", hollowWidth: "2.6px", imgHeight: "h-24 sm:h-32" },
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
  const [imageMissing, setImageMissing] = useState(false);
  const imgRef = useRef<HTMLImageElement>(null);
  const { text, hollowWidth, imgHeight } = SIZES[size];
  const alignClass = align === "center" ? "items-center" : "items-start";

  // The browser starts loading <img src> as soon as it parses the
  // server-rendered HTML — often before React hydrates and attaches the
  // onError listener below, so a 404 can be missed entirely. Catch that
  // race by checking the already-settled state once mounted.
  useEffect(() => {
    const img = imgRef.current;
    if (img && img.complete && img.naturalWidth === 0) {
      setImageMissing(true);
    }
  }, []);

  if (!imageMissing) {
    return (
      <span className={`inline-flex flex-col ${alignClass} ${className ?? ""}`}>
        {/* Plain <img>, not next/image: we don't know the real file's exact
            dimensions ahead of time, and this file is tiny/local anyway. */}
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          ref={imgRef}
          src="/logo.png"
          alt="INNOVA — Pracownia kreatywno-edukacyjna"
          className={`${imgHeight} w-auto`}
          onError={() => setImageMissing(true)}
        />
      </span>
    );
  }

  return (
    <span className={`inline-flex flex-col ${alignClass} ${className ?? ""}`}>
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
