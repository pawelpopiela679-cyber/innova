import { prisma } from "@/lib/db";

export type SessionWithAvailability = {
  id: string;
  title: string;
  description: string | null;
  startsAt: Date;
  endsAt: Date;
  capacity: number;
  confirmedCount: number;
  spotsLeft: number;
  isFull: boolean;
  instructorName: string;
  meetingUrl: string | null;
  status: "SCHEDULED" | "CANCELED";
  classType: { id: string; key: string; name: string; color: string };
};

/**
 * Loads sessions in [from, to) with their live confirmed-enrollment count,
 * used for both the public calendar and the instructor/admin availability
 * views ("what's free on this day / week / month").
 */
export async function getSessionsWithAvailability(
  from: Date,
  to: Date,
  opts: { classTypeId?: string; instructorId?: string } = {}
): Promise<SessionWithAvailability[]> {
  const sessions = await prisma.classSession.findMany({
    where: {
      startsAt: { gte: from, lt: to },
      ...(opts.classTypeId ? { classTypeId: opts.classTypeId } : {}),
      ...(opts.instructorId ? { instructorId: opts.instructorId } : {}),
    },
    include: {
      classType: true,
      enrollments: { where: { status: "CONFIRMED" }, select: { id: true } },
    },
    orderBy: { startsAt: "asc" },
  });

  return sessions.map((s) => {
    const confirmedCount = s.enrollments.length;
    const spotsLeft = Math.max(s.capacity - confirmedCount, 0);
    return {
      id: s.id,
      title: s.title,
      description: s.description,
      startsAt: s.startsAt,
      endsAt: s.endsAt,
      capacity: s.capacity,
      confirmedCount,
      spotsLeft,
      isFull: spotsLeft === 0,
      instructorName: s.instructorName,
      meetingUrl: s.meetingUrl,
      status: s.status,
      classType: {
        id: s.classType.id,
        key: s.classType.key,
        name: s.classType.name,
        color: s.classType.color,
      },
    };
  });
}
