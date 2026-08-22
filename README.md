# Pracownia Innova — system zapisów na zajęcia dla dzieci

Aplikacja webowa do zarządzania zapisami dzieci na zajęcia online w kreatywnej
pracowni. Zbudowana w Next.js (App Router) + TypeScript + Prisma + SQLite.

## Funkcje

- **Konta rodziców** — rejestracja, logowanie, dodawanie wielu dzieci do
  konta.
- **Kalendarz zajęć** (widok dzień / tydzień / miesiąc) z opisem zajęć na
  dany dzień i liczbą wolnych miejsc.
- **Zapisy z potwierdzeniem** — po zapisaniu dziecka rodzic od razu widzi
  ekran potwierdzenia i dostaje e-mail z potwierdzeniem (lub informacją o
  liście rezerwowej, gdy grupa jest pełna).
- **Powiadomienie e-mail dla pracowni** — każdy nowy zapis wysyła
  automatyczne powiadomienie na adres `STUDIO_NOTIFY_EMAIL` z danymi dziecka,
  rodzica i wybranych zajęć.
- **Panel prowadzącego / administratora** — podgląd dostępności (wolnych
  miejsc) w widoku dnia, tygodnia i miesiąca, filtrowanie po rodzaju zajęć,
  dodawanie nowych terminów i odwoływanie zajęć.
- **Lista rezerwowa** — gdy grupa jest pełna, zapis trafia na listę
  rezerwową i automatycznie awansuje na potwierdzony, jeśli zwolni się
  miejsce (np. po anulowaniu przez innego rodzica).
- Cztery rodzaje zajęć na start: **robotyka**, **zajęcia kreatywne**,
  **zajęcia teatralne**, **język angielski** — łatwe do rozszerzenia o
  kolejne (patrz niżej).

## Stos technologiczny

- [Next.js 16](https://nextjs.org/) (App Router, Server Actions)
- [Prisma](https://www.prisma.io/) + SQLite (łatwo przełączyć na
  PostgreSQL/MySQL do produkcji)
- [Tailwind CSS 4](https://tailwindcss.com/)
- [Zod](https://zod.dev/) do walidacji danych z formularzy
- [nodemailer](https://nodemailer.com/) do wysyłki e-maili (z trybem
  deweloperskim logującym treść do konsoli, gdy SMTP nie jest skonfigurowane)
- Sesje logowania: JWT w bezpiecznym ciasteczku httpOnly ([jose](https://github.com/panva/jose))

## Szybki start (lokalnie)

Wymagany Node.js 20+.

```bash
npm install
cp .env.example .env      # i uzupełnij wartości, patrz niżej
npm run db:migrate         # utworzy bazę SQLite i tabele
npm run db:seed            # doda 4 rodzaje zajęć, konto admina i przykładowe terminy
npm run dev
```

Aplikacja wystartuje na [http://localhost:3000](http://localhost:3000).

### Konta utworzone przez seed

| Rola | E-mail | Hasło |
| --- | --- | --- |
| Administrator / właściciel pracowni | `admin@innova-pracownia.pl` (lub wartość `SEED_ADMIN_EMAIL`) | `ZmienMnie123!` (lub `SEED_ADMIN_PASSWORD`) |
| Prowadzący (4 konta, po jednym na rodzaj zajęć) | np. `marek@innova-pracownia.pl` | `Prowadzacy123!` |
| Przykładowy rodzic | `rodzic@example.com` | `Haslo123!` |

**Koniecznie zmień te hasła / usuń konta demo przed wdrożeniem na
produkcję.**

## Konfiguracja (`.env`)

Zobacz `.env.example` — najważniejsze zmienne:

- `DATABASE_URL` — połączenie do bazy (domyślnie plik SQLite).
- `AUTH_SECRET` — losowy, długi sekret do podpisywania sesji
  (`openssl rand -base64 48`).
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM` — dane
  serwera pocztowego do wysyłki e-maili. **Jeśli zostawisz je puste,
  e-maile nie będą wysyłane — ich treść pojawi się w logu serwera**, co jest
  wygodne przy pracy lokalnej.
- `STUDIO_NOTIFY_EMAIL` — adres (lub lista adresów po przecinku), na który
  przychodzi powiadomienie o każdym nowym zapisie. To jest to „powiadomienie
  na @ o nowym chętnym”, o które prosiłeś.
- `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` — dane konta administratora
  tworzonego przez `npm run db:seed`.

## Struktura projektu

```
prisma/schema.prisma       modele bazy danych (User, Child, ClassType,
                            ClassSession, Enrollment)
prisma/seed.ts              dane startowe: 4 rodzaje zajęć, konto admina,
                            prowadzący, przykładowe terminy na najbliższe
                            tygodnie
src/lib/auth.ts             hasła (bcrypt), sesje (JWT w ciasteczku)
src/lib/mailer.ts           wysyłka e-maili (potwierdzenie + powiadomienie
                            studia)
src/lib/availability.ts     obliczanie wolnych miejsc dla danego zakresu dat
src/lib/actions/*           Server Actions — cała logika zapisów, kont,
                            zarządzania terminami
src/app/kalendarz           publiczny kalendarz zajęć (dzień/tydzień/miesiąc)
src/app/panel               panel rodzica (dzieci, zapisy, potwierdzenia)
src/app/admin                panel prowadzącego/administratora (dostępność
                             terminów, dodawanie/odwoływanie zajęć)
```

## Dodawanie kolejnych rodzajów zajęć

Rodzaje zajęć (`ClassType`) są danymi w bazie, nie są zaszyte na sztywno w
kodzie — nowy rodzaj można dodać bezpośrednio w bazie (np. przez
`npx prisma studio`) lub rozszerzając `prisma/seed.ts` o kolejny wpis.
Panel administratora (`/admin/zajecia/nowe`) pozwala dodawać nowe terminy do
istniejących rodzajów zajęć bez ingerencji w kod.

## Przydatne komendy

```bash
npm run dev          # serwer deweloperski
npm run build         # build produkcyjny
npm run start         # uruchomienie builda produkcyjnego
npm run lint           # ESLint
npm run db:migrate     # nowa migracja Prisma (dev)
npm run db:seed        # ponowne załadowanie danych startowych
npm run db:studio      # graficzny podgląd/edycja bazy danych (Prisma Studio)
```

## Uwagi dot. wdrożenia na produkcję

- Zamień `provider = "sqlite"` w `prisma/schema.prisma` na `postgresql` (lub
  inną docelową bazę) i ustaw prawdziwy `DATABASE_URL` — reszta kodu
  działa bez zmian.
- Skonfiguruj prawdziwe dane SMTP, inaczej e-maile nie będą faktycznie
  wysyłane (zobaczysz je jedynie w logach serwera).
- Ustaw unikalny, losowy `AUTH_SECRET`.
- Zmień hasła kont utworzonych przez seed (lub usuń konto demo rodzica).
