import { writeFile, mkdir, unlink } from "fs/promises";
import path from "path";

const ALLOWED_TYPES: Record<string, string> = {
  "image/png": "png",
  "image/jpeg": "jpg",
  "image/webp": "webp",
  "image/gif": "gif",
};
const MAX_BYTES = 3 * 1024 * 1024; // 3 MB

/**
 * Saves an uploaded image straight to `public/uploads/<subfolder>/`, so it's
 * served like any other static file — no cloud storage needed, works
 * unchanged on a VPS or shared "Node.js Selector" hosting (home.pl) as long
 * as the app directory is writable, which it is by default.
 *
 * Returns the public URL path to store on the record (e.g. in
 * `User.avatarUrl`), or null if no file was actually provided.
 * Throws on an oversized file or unsupported type.
 */
export async function saveUploadedImage(
  file: File | null,
  subfolder: string,
  baseName: string
): Promise<string | null> {
  if (!file || file.size === 0) return null;

  if (file.size > MAX_BYTES) {
    throw new Error("Zdjęcie jest za duże (maksymalnie 3 MB).");
  }
  const ext = ALLOWED_TYPES[file.type];
  if (!ext) {
    throw new Error("Dozwolone są tylko pliki PNG, JPG, WEBP lub GIF.");
  }

  const dir = path.join(process.cwd(), "public", "uploads", subfolder);
  await mkdir(dir, { recursive: true });

  const safeBase = baseName.replace(/[^a-zA-Z0-9_-]/g, "");
  const filename = `${safeBase}-${Date.now()}.${ext}`;
  const buffer = Buffer.from(await file.arrayBuffer());
  await writeFile(path.join(dir, filename), buffer);

  return `/uploads/${subfolder}/${filename}`;
}

/** Best-effort delete of a previously uploaded file — never throws. */
export async function deleteUploadedFile(publicUrl: string | null | undefined): Promise<void> {
  if (!publicUrl || !publicUrl.startsWith("/uploads/")) return;
  try {
    await unlink(path.join(process.cwd(), "public", publicUrl));
  } catch {
    // File already gone or never existed — fine.
  }
}
