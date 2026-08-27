import { z } from "zod";

export const registerSchema = z.object({
  name: z.string().trim().min(2, "Podaj imię i nazwisko."),
  email: z.string().trim().toLowerCase().email("Podaj poprawny adres e-mail."),
  phone: z.string().trim().optional().or(z.literal("")),
  password: z.string().min(8, "Hasło musi mieć co najmniej 8 znaków."),
});

export const loginSchema = z.object({
  email: z.string().trim().toLowerCase().email("Podaj poprawny adres e-mail."),
  password: z.string().min(1, "Podaj hasło."),
});

export const childSchema = z.object({
  firstName: z.string().trim().min(1, "Podaj imię dziecka."),
  lastName: z.string().trim().min(1, "Podaj nazwisko dziecka."),
  birthDate: z
    .string()
    .trim()
    .min(1, "Podaj datę urodzenia.")
    .refine((v) => !Number.isNaN(Date.parse(v)), "Nieprawidłowa data."),
  notes: z.string().trim().optional().or(z.literal("")),
});

export const enrollSchema = z.object({
  sessionId: z.string().min(1),
  childId: z.string().min(1),
});

export const createInstructorSchema = z.object({
  name: z.string().trim().min(2, "Podaj imię i nazwisko."),
  email: z.string().trim().toLowerCase().email("Podaj poprawny adres e-mail."),
  password: z.string().min(8, "Hasło musi mieć co najmniej 8 znaków."),
});

export const createSessionSchema = z
  .object({
    classTypeId: z.string().min(1, "Wybierz rodzaj zajęć."),
    title: z.string().trim().min(1, "Podaj nazwę zajęć."),
    date: z.string().trim().min(1, "Podaj datę."),
    startTime: z.string().trim().regex(/^\d{2}:\d{2}$/, "Podaj godzinę rozpoczęcia."),
    endTime: z.string().trim().regex(/^\d{2}:\d{2}$/, "Podaj godzinę zakończenia."),
    capacity: z.coerce
      .number()
      .int()
      .min(1, "Pojemność musi być co najmniej 1.")
      .max(10, "Grupa może liczyć maksymalnie 10 dzieci."),
    instructorName: z.string().trim().min(1, "Podaj prowadzącego."),
    meetingUrl: z.string().trim().optional().or(z.literal("")),
    description: z.string().trim().optional().or(z.literal("")),
    weeksCount: z.coerce
      .number()
      .int()
      .min(1, "Podaj co najmniej 1 tydzień.")
      .max(20, "Maksymalnie 20 tygodni na raz.")
      .default(1),
  })
  .refine((v) => v.startTime < v.endTime, {
    message: "Godzina zakończenia musi być po godzinie rozpoczęcia.",
    path: ["endTime"],
  });
