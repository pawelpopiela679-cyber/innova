import { PrismaClient } from "@prisma/client";
import bcrypt from "bcryptjs";
import {
  addWeeks,
  setHours,
  setMinutes,
  setSeconds,
  setMilliseconds,
  nextMonday,
  nextTuesday,
  nextWednesday,
  nextThursday,
  startOfDay,
} from "date-fns";

const prisma = new PrismaClient();

function at(date: Date, hours: number, minutes: number): Date {
  return setMilliseconds(setSeconds(setMinutes(setHours(date, hours), minutes), 0), 0);
}

/** Generates `count` weekly occurrences of a class starting from the next `anchor` weekday. */
function weeklyOccurrences(
  anchor: Date,
  startHour: number,
  startMinute: number,
  durationMinutes: number,
  count: number
): { startsAt: Date; endsAt: Date }[] {
  const out: { startsAt: Date; endsAt: Date }[] = [];
  for (let i = 0; i < count; i++) {
    const day = addWeeks(anchor, i);
    const startsAt = at(day, startHour, startMinute);
    const endsAt = new Date(startsAt.getTime() + durationMinutes * 60_000);
    out.push({ startsAt, endsAt });
  }
  return out;
}

async function main() {
  const today = startOfDay(new Date());

  // --- Studio owner / admin account ---
  const adminEmail = process.env.SEED_ADMIN_EMAIL || "admin@innova-pracownia.pl";
  const adminPassword = process.env.SEED_ADMIN_PASSWORD || "ZmienMnie123!";
  const admin = await prisma.user.upsert({
    where: { email: adminEmail },
    update: {},
    create: {
      email: adminEmail,
      name: "Właściciel Pracowni",
      role: "ADMIN",
      passwordHash: await bcrypt.hash(adminPassword, 12),
    },
  });
  console.log(`Admin: ${admin.email} / hasło: ${adminPassword}`);

  // --- Instructors ---
  const instructorDefs = [
    { email: "marek@innova-pracownia.pl", name: "Marek Kowalski" }, // robotyka
    { email: "ania@innova-pracownia.pl", name: "Ania Nowak" }, // kreatywne
    { email: "kasia@innova-pracownia.pl", name: "Kasia Wiśniewska" }, // teatralne
    { email: "ola@innova-pracownia.pl", name: "Ola Zielińska" }, // angielski
  ];
  const instructorPassword = "Prowadzacy123!";
  const instructors = [];
  for (const def of instructorDefs) {
    const user = await prisma.user.upsert({
      where: { email: def.email },
      update: {},
      create: {
        email: def.email,
        name: def.name,
        role: "INSTRUCTOR",
        passwordHash: await bcrypt.hash(instructorPassword, 12),
      },
    });
    instructors.push(user);
  }
  console.log(`Prowadzący hasło (wszyscy): ${instructorPassword}`);

  // --- Class types ---
  const classTypeDefs = [
    {
      key: "ROBOTICS",
      name: "Zajęcia z robotyki",
      description:
        "Budowanie i programowanie prostych robotów oraz automatów — dzieci uczą się podstaw elektroniki, logicznego myślenia i programowania blokowego w przyjaznej, praktycznej formie.",
      color: "#2563eb",
      ageMin: 7,
      ageMax: 13,
      instructor: instructors[0],
      weekly: () => weeklyOccurrences(nextWednesday(today), 17, 0, 60, 10),
      title: "Robotyka — grupa online",
      capacity: 8,
    },
    {
      key: "CREATIVE",
      name: "Zajęcia kreatywne",
      description:
        "Malarstwo, rękodzieło, prace plastyczne i eksperymenty z różnymi materiałami — rozwijamy wyobraźnię i zdolności manualne najmłodszych w luźnej, artystycznej atmosferze.",
      color: "#db2777",
      ageMin: 4,
      ageMax: 10,
      instructor: instructors[1],
      weekly: () => weeklyOccurrences(nextTuesday(today), 16, 0, 60, 10),
      title: "Pracownia kreatywna — grupa online",
      capacity: 10,
    },
    {
      key: "THEATER",
      name: "Zajęcia teatralne",
      description:
        "Improwizacja, dykcja, praca z ciałem i głosem oraz przygotowywanie krótkich etiud — zajęcia budujące pewność siebie, wyobraźnię i swobodę wyrażania emocji.",
      color: "#7c3aed",
      ageMin: 6,
      ageMax: 14,
      instructor: instructors[2],
      weekly: () => weeklyOccurrences(nextThursday(today), 17, 30, 60, 10),
      title: "Koło teatralne — grupa online",
      capacity: 12,
    },
    {
      key: "ENGLISH",
      name: "Zajęcia z języka angielskiego",
      description:
        "Nauka angielskiego przez zabawę, piosenki, gry i krótkie dialogi — zajęcia prowadzone w małych grupach, dopasowane do wieku i poziomu dziecka.",
      color: "#059669",
      ageMin: 5,
      ageMax: 12,
      instructor: instructors[3],
      weekly: () => weeklyOccurrences(nextMonday(today), 16, 30, 45, 10),
      title: "Angielski dla dzieci — grupa online",
      capacity: 8,
    },
  ];

  for (const def of classTypeDefs) {
    const classType = await prisma.classType.upsert({
      where: { key: def.key },
      update: {
        name: def.name,
        description: def.description,
        color: def.color,
        ageMin: def.ageMin,
        ageMax: def.ageMax,
      },
      create: {
        key: def.key,
        name: def.name,
        description: def.description,
        color: def.color,
        ageMin: def.ageMin,
        ageMax: def.ageMax,
      },
    });

    const occurrences = def.weekly();
    for (const occ of occurrences) {
      const existing = await prisma.classSession.findFirst({
        where: { classTypeId: classType.id, startsAt: occ.startsAt },
      });
      if (existing) continue;
      await prisma.classSession.create({
        data: {
          classTypeId: classType.id,
          title: def.title,
          startsAt: occ.startsAt,
          endsAt: occ.endsAt,
          capacity: def.capacity,
          instructorId: def.instructor.id,
          instructorName: def.instructor.name,
          meetingUrl: "https://meet.innova-pracownia.pl/demo-room",
        },
      });
    }
    console.log(`${def.name}: ${occurrences.length} terminów`);
  }

  // --- A demo parent + child + a couple of enrollments, so the app has
  // something to show right after seeding. ---
  const demoParent = await prisma.user.upsert({
    where: { email: "rodzic@example.com" },
    update: {},
    create: {
      email: "rodzic@example.com",
      name: "Testowy Rodzic",
      phone: "500600700",
      role: "PARENT",
      passwordHash: await bcrypt.hash("Haslo123!", 12),
    },
  });

  const demoChild = await prisma.child.upsert({
    where: { id: "demo-child-seed-1" },
    update: {},
    create: {
      id: "demo-child-seed-1",
      parentId: demoParent.id,
      firstName: "Zosia",
      lastName: "Testowa",
      birthDate: new Date("2018-05-14"),
    },
  });

  const firstRobotics = await prisma.classSession.findFirst({
    where: { classType: { key: "ROBOTICS" } },
    orderBy: { startsAt: "asc" },
  });
  if (firstRobotics) {
    await prisma.enrollment.upsert({
      where: { sessionId_childId: { sessionId: firstRobotics.id, childId: demoChild.id } },
      update: {},
      create: {
        sessionId: firstRobotics.id,
        childId: demoChild.id,
        parentId: demoParent.id,
        status: "CONFIRMED",
      },
    });
  }

  console.log(`Demo rodzic: rodzic@example.com / Haslo123!`);
  console.log("Seed zakończony.");
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
