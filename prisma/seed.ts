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
  nextFriday,
  nextSaturday,
  startOfDay,
} from "date-fns";

const prisma = new PrismaClient();

const MAX_GROUP_SIZE = 10; // studio rule: max 10 dzieci na grupę

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

/**
 * One bookable weekly group for a specific age bracket / variant of a class
 * type — mirrors a row in the studio's cennik (oferta i cennik), but also
 * carries the scheduling info needed to actually generate ClassSession rows.
 * Each group is capped at MAX_GROUP_SIZE.
 */
type GroupDef = {
  label: string; // wariant name, e.g. "Mix kreatywny" — "" when the age bracket is the only variant
  ageLabel: string; // e.g. "5–7 lat"
  durationMin: number;
  priceMonthly: number;
  oneTimeFee?: number;
  weekday: (base: Date) => Date; // nextMonday, nextTuesday, ...
  startHour: number;
  startMinute: number;
};

type ClassTypeDef = {
  key: string;
  name: string;
  description: string;
  color: string;
  ageMin: number;
  ageMax: number;
  instructorEmail: string;
  groups: GroupDef[];
};

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
    { email: "ola@innova-pracownia.pl", name: "Ola Zielińska" }, // angielski
    { email: "kasia@innova-pracownia.pl", name: "Kasia Wiśniewska" }, // zajęcia sceniczne
    { email: "marek@innova-pracownia.pl", name: "Marek Kowalski" }, // robotyka
    { email: "ania@innova-pracownia.pl", name: "Ania Nowak" }, // zajęcia kreatywne
    { email: "beata@innova-pracownia.pl", name: "Beata Kowalczyk" }, // matematyka
    { email: "tomek@innova-pracownia.pl", name: "Tomek Nowicki" }, // eksperymentatorium
  ];
  const instructorPassword = "Prowadzacy123!";
  const instructorsByEmail = new Map<string, Awaited<ReturnType<typeof prisma.user.upsert>>>();
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
    instructorsByEmail.set(def.email, user);
  }
  console.log(`Prowadzący hasło (wszyscy): ${instructorPassword}`);

  // --- Class types + realne grupy wiekowe (dopasowane do ulotki INNOVA —
  // Oferta i cennik). Każda grupa to osobny, cotygodniowy termin z limitem
  // ${MAX_GROUP_SIZE} dzieci. ---
  const classTypeDefs: ClassTypeDef[] = [
    {
      key: "ENGLISH",
      name: "Angielski",
      description:
        "Nauka angielskiego przez zabawę, piosenki, gry i krótkie dialogi — zajęcia prowadzone w małych grupach, dopasowane do wieku i poziomu dziecka.",
      color: "#6badd9",
      ageMin: 3,
      ageMax: 7,
      instructorEmail: "ola@innova-pracownia.pl",
      groups: [
        {
          label: "",
          ageLabel: "3–4 lata",
          durationMin: 35,
          priceMonthly: 149,
          weekday: nextMonday,
          startHour: 16,
          startMinute: 0,
        },
        {
          label: "",
          ageLabel: "5–7 lat",
          durationMin: 50,
          priceMonthly: 199,
          weekday: nextMonday,
          startHour: 16,
          startMinute: 45,
        },
      ],
    },
    {
      key: "THEATER",
      name: "Zajęcia sceniczne",
      description:
        "Improwizacja, dykcja, praca z ciałem i głosem oraz przygotowywanie krótkich etiud — zajęcia budujące pewność siebie, wyobraźnię i swobodę wyrażania emocji.",
      color: "#ab93dd",
      ageMin: 6,
      ageMax: 15,
      instructorEmail: "kasia@innova-pracownia.pl",
      groups: [
        {
          label: "Scena dla każdego",
          ageLabel: "6–9 lat",
          durationMin: 60,
          priceMonthly: 199,
          weekday: nextThursday,
          startHour: 17,
          startMinute: 0,
        },
        {
          label: "Scena dla każdego",
          ageLabel: "10–15 lat",
          durationMin: 75,
          priceMonthly: 229,
          weekday: nextThursday,
          startHour: 18,
          startMinute: 15,
        },
        {
          label: "Słowo na scenie",
          ageLabel: "9–15 lat",
          durationMin: 75,
          priceMonthly: 249,
          weekday: nextThursday,
          startHour: 19,
          startMinute: 40,
        },
      ],
    },
    {
      key: "ROBOTICS",
      name: "Robotyka",
      description:
        "Budowanie i programowanie prostych robotów oraz automatów — dzieci uczą się podstaw elektroniki, logicznego myślenia i programowania blokowego w przyjaznej, praktycznej formie.",
      color: "#57a3b3",
      ageMin: 5,
      ageMax: 10,
      instructorEmail: "marek@innova-pracownia.pl",
      groups: [
        {
          label: "",
          ageLabel: "5–7 lat",
          durationMin: 60,
          priceMonthly: 249,
          weekday: nextWednesday,
          startHour: 17,
          startMinute: 0,
        },
        {
          label: "",
          ageLabel: "8–10 lat",
          durationMin: 60,
          priceMonthly: 249,
          weekday: nextWednesday,
          startHour: 18,
          startMinute: 15,
        },
      ],
    },
    {
      key: "CREATIVE",
      name: "Zajęcia kreatywne",
      description:
        "Malarstwo, rękodzieło, prace plastyczne i eksperymenty z różnymi materiałami — rozwijamy wyobraźnię i zdolności manualne najmłodszych w luźnej, artystycznej atmosferze.",
      color: "#e79d94",
      ageMin: 5,
      ageMax: 15,
      instructorEmail: "ania@innova-pracownia.pl",
      groups: [
        {
          label: "Mix kreatywny",
          ageLabel: "5–7 lat",
          durationMin: 50,
          priceMonthly: 229,
          weekday: nextTuesday,
          startHour: 16,
          startMinute: 0,
        },
        {
          label: "Mix kreatywny",
          ageLabel: "8–11 lat",
          durationMin: 60,
          priceMonthly: 229,
          weekday: nextTuesday,
          startHour: 17,
          startMinute: 0,
        },
        {
          label: "Szydełkowanie / haft",
          ageLabel: "9–15 lat",
          durationMin: 75,
          priceMonthly: 229,
          oneTimeFee: 79,
          weekday: nextTuesday,
          startHour: 18,
          startMinute: 15,
        },
      ],
    },
    {
      key: "MATH",
      name: "Matematyka",
      description:
        "Matematyczne odkrycia przez zabawę, oswajanie z liczbami i logiczne myślenie dla najmłodszych, a dla starszych — pomoc szkolna, nadrabianie zaległości i przygotowanie do egzaminu ósmoklasisty.",
      color: "#e0b463",
      ageMin: 4,
      ageMax: 15,
      instructorEmail: "beata@innova-pracownia.pl",
      groups: [
        {
          label: "Matematyczne odkrycia",
          ageLabel: "4–5 lat",
          durationMin: 35,
          priceMonthly: 149,
          weekday: nextFriday,
          startHour: 16,
          startMinute: 0,
        },
        {
          label: "Matematyka bez stresu",
          ageLabel: "6–8 lat",
          durationMin: 50,
          priceMonthly: 199,
          weekday: nextFriday,
          startHour: 16,
          startMinute: 45,
        },
        {
          label: "Logika + pomoc szkolna",
          ageLabel: "klasy 1–3",
          durationMin: 60,
          priceMonthly: 199,
          weekday: nextFriday,
          startHour: 17,
          startMinute: 45,
        },
        {
          label: "Kurs E8",
          ageLabel: "klasa 8",
          durationMin: 75,
          priceMonthly: 249,
          weekday: nextFriday,
          startHour: 19,
          startMinute: 0,
        },
      ],
    },
    {
      key: "SCIENCE",
      name: "Eksperymentatorium",
      description:
        "Bezpieczne eksperymenty chemiczne i fizyczne, które tłumaczą, jak działa świat — dzieci samodzielnie odkrywają zjawiska naukowe pod okiem prowadzącego, ucząc się przez działanie.",
      color: "#64bd9c",
      ageMin: 6,
      ageMax: 15,
      instructorEmail: "tomek@innova-pracownia.pl",
      groups: [
        {
          label: "",
          ageLabel: "6–9 lat",
          durationMin: 60,
          priceMonthly: 229,
          weekday: nextSaturday,
          startHour: 11,
          startMinute: 0,
        },
        {
          label: "",
          ageLabel: "10–15 lat",
          durationMin: 75,
          priceMonthly: 249,
          weekday: nextSaturday,
          startHour: 12,
          startMinute: 15,
        },
      ],
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

    const instructor = instructorsByEmail.get(def.instructorEmail);
    if (!instructor) throw new Error(`Brak prowadzącego: ${def.instructorEmail}`);

    // Reload the pricing table from scratch each run so the seed stays the
    // single source of truth for what's shown on the offer page.
    await prisma.pricingTier.deleteMany({ where: { classTypeId: classType.id } });
    await prisma.pricingTier.createMany({
      data: def.groups.map((g, i) => ({
        classTypeId: classType.id,
        label: g.label,
        ageLabel: g.ageLabel,
        durationMin: g.durationMin,
        priceMonthly: g.priceMonthly,
        oneTimeFee: g.oneTimeFee,
        sortOrder: i,
      })),
    });

    let totalOccurrences = 0;
    for (const group of def.groups) {
      // Class type name is already shown alongside the title everywhere in the
      // UI (badge/dot), so keep the title itself to just the group descriptor.
      const title = `${group.label ? `${group.label} — ` : ""}${group.ageLabel}`;
      const occurrences = weeklyOccurrences(
        group.weekday(today),
        group.startHour,
        group.startMinute,
        group.durationMin,
        10
      );
      for (const occ of occurrences) {
        const existing = await prisma.classSession.findFirst({
          where: { classTypeId: classType.id, title, startsAt: occ.startsAt },
        });
        if (existing) continue;
        await prisma.classSession.create({
          data: {
            classTypeId: classType.id,
            title,
            startsAt: occ.startsAt,
            endsAt: occ.endsAt,
            capacity: MAX_GROUP_SIZE,
            instructorId: instructor.id,
            instructorName: instructor.name,
            meetingUrl: "https://meet.innova-pracownia.pl/demo-room",
          },
        });
      }
      totalOccurrences += occurrences.length;
    }
    console.log(
      `${def.name}: ${def.groups.length} grup, ${totalOccurrences} terminów łącznie`
    );
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
        confirmedAt: new Date(),
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
