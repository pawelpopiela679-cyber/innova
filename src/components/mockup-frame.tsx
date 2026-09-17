import Image from "next/image";

/**
 * Renders one of the studio's own mockup screenshots (public/mockups) as a
 * full-bleed, aspect-ratio-locked background, with real interactive
 * elements (nav links, form fields, buttons...) positioned on top of it as
 * percentage-based overlays so they land in the same spot at any viewport
 * width. All mockups share the same 1672×941 design canvas.
 */
export function MockupFrame({
  src,
  alt,
  children,
  className,
}: {
  src: string;
  alt: string;
  children?: React.ReactNode;
  className?: string;
}) {
  return (
    <div
      className={`relative mx-auto w-full ${className ?? ""}`}
      style={{ aspectRatio: "1672 / 941" }}
    >
      <Image src={src} alt={alt} fill priority sizes="100vw" className="object-cover" />
      <div className="absolute inset-0">{children}</div>
    </div>
  );
}
