import { prisma } from "@/lib/db";
import { DashedDivider } from "@/components/decor";

export default async function AboutUsPage() {
  const instructors = await prisma.user.findMany({
    where: { role: "INSTRUCTOR" },
    orderBy: { name: "asc" },
  });

  return (
    <div className="mx-auto max-w-4xl px-4 py-12">
      <h1 className="text-center font-heading text-3xl font-extrabold">
        Poznaj <span className="text-[var(--coral)]">nas</span>
      </h1>
      <DashedDivider className="mx-auto mt-4 w-40" />
      <p className="mx-auto mt-4 max-w-xl text-center text-[var(--muted)]">
        Zespół prowadzących pracowni INNOVA — ta strona aktualizuje się automatycznie, gdy
        zakładamy lub edytujemy konta prowadzących.
      </p>

      {instructors.length === 0 ? (
        <p className="mt-10 text-center text-[var(--muted)]">
          Wkrótce przedstawimy tu nasz zespół.
        </p>
      ) : (
        <div className="mt-10 grid gap-6 sm:grid-cols-2">
          {instructors.map((i) => (
            <div
              key={i.id}
              className="flex gap-4 rounded-2xl border border-[var(--border)] bg-[var(--surface)] p-5"
            >
              {i.avatarUrl ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  src={i.avatarUrl}
                  alt={i.name}
                  className="h-20 w-20 shrink-0 rounded-full object-cover"
                />
              ) : (
                <div className="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-[var(--sage-soft)] text-3xl font-bold text-[var(--sage)]">
                  {i.name.charAt(0)}
                </div>
              )}
              <div>
                <h2 className="font-heading font-bold">{i.name}</h2>
                {i.bio && <p className="mt-1 text-sm text-[var(--muted)]">{i.bio}</p>}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
