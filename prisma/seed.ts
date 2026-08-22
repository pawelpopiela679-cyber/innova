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

type PricingTierDef = {
  label: string;
  ageLabel: string;
  durationMin: number;
  priceMonthly: number;
  oneTimeFee?: number;
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

  // --- Class types (dopasowane do ulotki INNOVA — Oferta i cennik) ---
  const classTypeDefs = [
    {
      key: "ENGLISH",
      name: "Angielski",
      description:
        "Nauka angielskiego przez zabawę, piosenki, gry i krótkie dialogi — zajęcia prowadzone w małych grupach, dopasowane do wieku i poziomu dziecka.",
      color: "#4f9bd1",
      ageMin: 3,
      ageMax: 7,
      instructor: instructors[0],
      weekly: () => weeklyOccurrences(nextMonday(today), 16, 30, 50, 10),
      title: "Angielski — grupa online",
      capacity: 8,
      pricing: [
        { label: "", ageLabel: "3–4 lata", durationMin: 35, priceMonthly: 149 },
        { label: "", ageLabel: "5–7 lat", durationMin: 50, priceMonthly: 199 },
      ] satisfies PricingTierDef[],
    },
    {
      key: "THEATER",
      name: "Zajęcia sceniczne",
      description:
        "Improwizacja, dykcja, praca z ciałem i głosem oraz przygotowywanie krótkich etiud — zajęcia budujące pewność siebie, wyobraźnię i swobodę wyrażania emocji.",
      color: "#9b7fd4",
      ageMin: 6,
      ageMax: 15,
      instructor: instructors[1],
      weekly: () => weeklyOccurrences(nextThursday(today), 17, 30, 60, 10),
      title: "Zajęcia sceniczne — grupa online",
      capacity: 12,
      pricing: [
        { label: "Słowo na scenie", ageLabel: "9–15 lat", durationMin: 75, priceMonthly: 249 },
        { label: "Scena dla każdego", ageLabel: "6–9 lat", durationMin: 60, priceMonthly: 199 },
        { label: "Scena dla każdego", ageLabel: "10–15 lat", durationMin: 75, priceMonthly: 229 },
      ] satisfies PricingTierDef[],
    },
    {
      key: "ROBOTICS",
      name: "Robotyka",
      description:
        "Budowanie i programowanie prostych robotów oraz automatów — dzieci uczą się podstaw elektroniki, logicznego myślenia i programowania blokowego w przyjaznej, praktycznej formie.",
      color: "#3f8fa0",
      ageMin: 5,
      ageMax: 10,
      instructor: instructors[2],
      weekly: () => weeklyOccurrences(nextWednesday(today), 17, 0, 60, 10),
      title: "Robotyka — grupa online",
      capacity: 8,
      pricing: [
        { label: "", ageLabel: "5–7 lat", durationMin: 60, priceMonthly: 249 },
        { label: "", ageLabel: "8–10 lat", durationMin: 60, priceMonthly: 249 },
      ] satisfies PricingTierDef[],
    },
    {
      key: "CREATIVE",
      name: "Zajęcia kreatywne",
      description:
        "Malarstwo, rękodzieło, prace plastyczne i eksperymenty z różnymi materiałami — rozwijamy wyobraźnię i zdolności manualne najmłodszych w luźnej, artystycznej atmosferze.",
      color: "#e08a80",
      ageMin: 5,
      ageMax: 15,
      instructor: instructors[3],
      weekly: () => weeklyOccurrences(nextTuesday(today), 16, 0, 60, 10),
      title: "Pracownia kreatywna — grupa online",
      capacity: 10,
      pricing: [
        { label: "Mix kreatywny", ageLabel: "5–7 lat", durationMin: 50, priceMonthly: 229 },
        { label: "Mix kreatywny", ageLabel: "8–11 lat", durationMin: 60, priceMonthly: 229 },
        {
          label: "Szydełkowanie / haft",
          ageLabel: "9–15 lat",
          durationMin: 75,
          priceMonthly: 229,
          oneTimeFee: 79,
        },
      ] satisfies PricingTierDef[],
    },
    {
      key: "MATH",
      name: "Matematyka",
      description:
        "Matematyczne odkrycia przez zabawę, oswajanie z liczbami i logiczne myślenie dla najmłodszych, a dla starszych — pomoc szkolna, nadrabianie zaległości i przygotowanie do egzaminu ósmoklasisty.",
      color: "#d9a441",
      ageMin: 4,
      ageMax: 15,
      instructor: instructors[4],
      weekly: () => weeklyOccurrences(nextFriday(today), 16, 0, 60, 10),
      title: "Matematyka — grupa online",
      capacity: 8,
      pricing: [
        {
          label: "Matematyczne odkrycia",
          ageLabel: "4–5 lat",
          durationMin: 35,
          priceMonthly: 149,
        },
        {
          label: "Matematyka bez stresu",
          ageLabel: "6–8 lat",
          durationMin: 50,
          priceMonthly: 199,
        },
        {
          label: "Logika + pomoc szkolna",
          ageLabel: "klasy 1–3",
          durationMin: 60,
          priceMonthly: 199,
        },
        { label: "Kurs E8", ageLabel: "klasa 8", durationMin: 75, priceMonthly: 249 },
      ] satisfies PricingTierDef[],
    },
    {
      key: "SCIENCE",
      name: "Eksperymentatorium",
      description:
        "Bezpieczne eksperymenty chemiczne i fizyczne, które tłumaczą, jak działa świat — dzieci samodzielnie odkrywają zjawiska naukowe pod okiem prowadzącego, ucząc się przez działanie.",
      color: "#4fae8a",
      ageMin: 6,
      ageMax: 15,
      instructor: instructors[5],
      weekly: () => weeklyOccurrences(nextSaturday(today), 11, 0, 60, 10),
      title: "Eksperymentatorium — grupa online",
      capacity: 8,
      pricing: [
        { label: "", ageLabel: "6–9 lat", durationMin: 60, priceMonthly: 229 },
        { label: "", ageLabel: "10–15 lat", durationMin: 75, priceMonthly: 249 },
      ] satisfies PricingTierDef[],
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

    // Reload the pricing table from scratch each run so the seed stays the
    // single source of truth for what's shown on the offer page.
    await prisma.pricingTier.deleteMany({ where: { classTypeId: classType.id } });
    await prisma.pricingTier.createMany({
      data: def.pricing.map((tier, i) => ({
        classTypeId: classType.id,
        label: tier.label,
        ageLabel: tier.ageLabel,
        durationMin: tier.durationMin,
        priceMonthly: tier.priceMonthly,
        oneTimeFee: tier.oneTimeFee,
        sortOrder: i,
      })),
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
    console.log(`${def.name}: ${occurrences.length} terminów, ${def.pricing.length} wariantów cenowych`);
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
