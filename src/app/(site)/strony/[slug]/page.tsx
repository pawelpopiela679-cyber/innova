import { notFound } from "next/navigation";
import { prisma } from "@/lib/db";
import { DashedDivider } from "@/components/decor";

export default async function CustomPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const page = await prisma.page.findUnique({ where: { slug } });
  if (!page) notFound();

  const paragraphs = page.content.split(/\n\s*\n/).map((p) => p.trim()).filter(Boolean);

  return (
    <div className="mx-auto max-w-2xl px-4 py-12">
      <h1 className="text-center font-heading text-3xl font-extrabold">{page.title}</h1>
      <DashedDivider className="mx-auto mt-4 w-40" />
      <div className="mt-8 space-y-4">
        {paragraphs.map((p, i) => (
          <p key={i} className="whitespace-pre-line leading-relaxed">
            {p}
          </p>
        ))}
      </div>
    </div>
  );
}
