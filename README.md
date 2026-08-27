# INNOVA — system zapisów na zajęcia dla dzieci

Aplikacja webowa do zarządzania zapisami dzieci na zajęcia online dla
pracowni kreatywno-edukacyjnej INNOVA (Czechowice-Dziedzice). Zbudowana w
Next.js (App Router) + TypeScript + Prisma + SQLite. Kolorystyka, czcionki i
treść oferty dopasowane do materiałów promocyjnych pracowni.

> **Masz hosting home.pl bez pakietu Node.js?** W folderze [`php/`](./php)
> jest **druga, w pełni samodzielna wersja tej samej aplikacji** napisana w
> czystym PHP + MySQL — dokładnie ten sam wygląd i te same funkcje, ale
> działa na zwykłym hostingu współdzielonym bez Node.js, SSH czy Composera.
> Zacznij od [`php/README_PHP.md`](./php/README_PHP.md) i
> [`php/DEPLOY_HOMEPL.md`](./php/DEPLOY_HOMEPL.md).

## Funkcje

- **Konta rodziców** — rejestracja, logowanie, dodawanie wielu dzieci do
  konta.
- **Kalendarz zajęć** (widok dzień / tydzień / miesiąc) z opisem zajęć na
  dany dzień i liczbą wolnych miejsc.
- **Zgłoszenia wymagające potwierdzenia** — rodzic zgłasza chęć zapisu na
  wybrany termin; nic nie jest automatycznie potwierdzane. Zgłoszenie trafia
  do pracowni jako „oczekujące” (`PENDING`).
- **Powiadomienie e-mail dla pracowni** — każde nowe zgłoszenie wysyła
  automatyczne powiadomienie na adres `STUDIO_NOTIFY_EMAIL` z **wiekiem
  dziecka** (wyliczonym z daty urodzenia), danymi rodzica i wybranymi
  zajęciami — żeby łatwo dobrać właściwą grupę wiekową.
- **Panel zgłoszeń** (`/admin/zapisy`) — pracownia przegląda zgłoszenia,
  widzi wiek dziecka i zajętość grupy, po czym: potwierdza do wybranej grupy
  (lub od razu **przypisuje inną grupę tego samego rodzaju zajęć**, jeśli
  wiek pasuje lepiej gdzie indziej), przenosi na listę rezerwową albo
  odrzuca. Rodzic za każdym razem dostaje e-mail z wynikiem.
- **Grupy z limitem 10 dzieci** — każdy termin (`ClassSession`) to osobna
  grupa wiekowa z twardym limitem miejsc.
- **Każdy prowadzący dodaje własny grafik** (`/admin/zajecia/nowe`) — dzień,
  godzina, liczba tygodni do powtórzenia (system sam utworzy cały
  cotygodniowy cykl) i limit miejsc. Prowadzący loguje się na swoje konto i
  widzi/zarządza tylko tym, co dodał.
- **Master admin (właściciel pracowni)** — konto z rolą `ADMIN` widzi
  wszystko co prowadzący, a dodatkowo:
  - zakłada, **edytuje i usuwa** konta prowadzących w `/admin/prowadzacy`
    (e-mail, hasło, **zdjęcie profilowe**, krótka notka),
  - zmienia **kolorystykę całej strony** bez dotykania kodu w
    `/admin/wyglad` (kolory zapisują się w bazie i obowiązują natychmiast),
  - dodaje **własne podstrony** (np. „Regulamin”, „FAQ”) w `/admin/strony`
    — trafiają automatycznie do menu nawigacji.
- **„Mój profil”** (`/admin/profil`) — każdy zalogowany prowadzący/admin sam
  zmienia swoją nazwę wyświetlaną, e-mail, zdjęcie, notkę i hasło (wymaga
  podania obecnego hasła). To tutaj właściciel pracowni zmienia domyślną,
  wpisaną przez seed nazwę „Właściciel Pracowni” na swoje prawdziwe imię.
- **Strona „Poznaj nas”** (`/poznaj-nas`) — zespół prowadzących ze
  zdjęciami i notkami, generowany automatycznie z kont prowadzących
  (edytujesz w `/admin/prowadzacy`, strona się sama aktualizuje).
- **Panel prowadzącego / administratora** — podgląd dostępności (wolnych
  miejsc) w widoku dnia, tygodnia i miesiąca, filtrowanie po rodzaju zajęć,
  dodawanie nowych terminów i odwoływanie zajęć.
- **Lista rezerwowa** — gdy grupa jest pełna, zgłoszenie można przenieść na
  listę rezerwową; automatycznie awansuje na potwierdzone, jeśli zwolni się
  miejsce (np. po anulowaniu przez innego rodzica lub przeniesieniu kogoś do
  innej grupy).
- **Cennik na stronie ofertowej** (`/zajecia`) — warianty wiekowe, czas
  trwania i cena miesięczna dla każdego rodzaju zajęć (dane informacyjne,
  aplikacja nie obsługuje płatności).
- Sześć rodzajów zajęć na start: **angielski**, **zajęcia sceniczne**,
  **robotyka**, **zajęcia kreatywne**, **matematyka**, **eksperymentatorium**
  — łatwe do rozszerzenia o kolejne (patrz niżej).

## Prawdziwe logo (plik graficzny)

Domyślnie strona pokazuje wordmark odtworzony w kodzie (przybliżenie
kolorystyczne). Żeby pokazać **dokładnie Wasz plik z logo**, zapisz go jako:

```
public/logo.png
```

(najlepiej PNG z przezroczystym tłem). Strona automatycznie zacznie
wyświetlać ten plik wszędzie — w nawigacji, na stronie głównej i w stopce —
bez żadnych zmian w kodzie. Jeśli pliku nie ma, strona po cichu wraca do
wersji odtworzonej w kodzie (`src/components/logo.tsx`).

## Dane kontaktowe pracowni

Adres, telefon i linki do social media (widoczne w stopce strony) są wpisane
na stałe w `src/components/footer.tsx` — jeśli się zmienią, zaktualizuj je
tam:

- ul. Kolejowa, Czechowice-Dziedzice
- tel. 570 250 363
- Facebook: `/innova.pracownia`, Instagram: `/innova_pracownia`
- www.innova-pracownia.pl

## Stos technologiczny

- [Next.js 16](https://nextjs.org/) (App Router, Server Actions)
- [Prisma](https://www.prisma.io/) + SQLite (łatwo przełączyć na
  PostgreSQL/MySQL do produkcji)
- [Tailwind CSS 4](https://tailwindcss.com/)
- [Zod](https://zod.dev/) do walidacji danych z formularzy
- [nodemailer](https://nodemailer.com/) do wysyłki e-maili (z trybem
  deweloperskim logującym treść do konsoli, gdy SMTP nie jest skonfigurowane)
- Sesje logowania: JWT w bezpiecznym ciasteczku httpOnly ([jose](https://github.com/panva/jose))
- Czcionki (Google Fonts, ładowane przez `next/font/google`): **Fredoka**
  (nagłówki/logo), **Caveat** (odręczny akcent, np. "Miejsce rozwoju"),
  **Nunito** (tekst)

## Szybki start (lokalnie)

Wymagany Node.js 20+.

```bash
npm install
cp .env.example .env      # i uzupełnij wartości, patrz niżej
npm run db:migrate         # utworzy bazę SQLite i tabele
npm run db:seed            # doda 6 rodzajów zajęć, cennik, konto admina i przykładowe terminy
npm run dev
```

Aplikacja wystartuje na [http://localhost:3000](http://localhost:3000).

### Konta utworzone przez seed

| Rola | E-mail | Hasło |
| --- | --- | --- |
| Administrator / właściciel pracowni | `admin@innova-pracownia.pl` (lub wartość `SEED_ADMIN_EMAIL`) | `ZmienMnie123!` (lub `SEED_ADMIN_PASSWORD`) |
| ↳ to jedyne konto z rolą `ADMIN` — po zalogowaniu zmień w `/admin/profil` domyślną nazwę „Właściciel Pracowni” na swoje imię i nazwisko oraz hasło. | | |
| Prowadzący (6 kont, po jednym na rodzaj zajęć) | np. `marek@innova-pracownia.pl` | `Prowadzacy123!` |
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
                            PricingTier, ClassSession, Enrollment)
prisma/seed.ts              dane startowe: 6 rodzajów zajęć + cennik, konto
                            admina, prowadzący, przykładowe terminy na
                            najbliższe tygodnie
src/lib/auth.ts             hasła (bcrypt), sesje (JWT w ciasteczku)
src/lib/mailer.ts           wysyłka e-maili (zgłoszenie/potwierdzenie/
                            odrzucenie + powiadomienie studia)
src/lib/age.ts               liczenie wieku dziecka z daty urodzenia
src/lib/availability.ts     obliczanie wolnych miejsc dla danego zakresu dat
src/lib/enrollment-helpers.ts  wspólna logika awansu z listy rezerwowej
src/lib/actions/*           Server Actions — cała logika zapisów, kont,
                            zarządzania terminami
src/app/kalendarz           publiczny kalendarz zajęć (dzień/tydzień/miesiąc)
src/app/panel               panel rodzica (dzieci, zgłoszenia, potwierdzenia)
src/app/admin                panel prowadzącego/administratora: /admin/zapisy
                             (przegląd zgłoszeń, potwierdzanie, przypisywanie
                             grup), dostępność terminów, dodawanie/odwoływanie
                             zajęć
```

## Dodawanie kolejnych rodzajów zajęć

Rodzaje zajęć (`ClassType`) i pozycje cennika (`PricingTier`) są danymi w
bazie, nie są zaszyte na sztywno w kodzie — nowy rodzaj (razem z wariantami
cenowymi) można dodać bezpośrednio w bazie (np. przez `npx prisma studio`)
lub rozszerzając `prisma/seed.ts` o kolejny wpis, a potem uruchamiając
ponownie `npm run db:seed`. Panel administratora (`/admin/zajecia/nowe`)
pozwala dodawać nowe terminy do istniejących rodzajów zajęć bez ingerencji w
kod.

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

- **Wdrożenie na home.pl (hosting z obsługą Node.js): pełna instrukcja krok
  po kroku w [`DEPLOY.md`](./DEPLOY.md)**, razem z gotowym plikiem
  `server.js` potrzebnym na tego typu hostingu.
- Zamień `provider = "sqlite"` w `prisma/schema.prisma` na `mysql` (zalecane
  na hostingu współdzielonym, patrz `DEPLOY.md`) albo `postgresql` (VPS/
  chmura) i ustaw prawdziwy `DATABASE_URL` — reszta kodu działa bez zmian.
- Skonfiguruj prawdziwe dane SMTP, inaczej e-maile nie będą faktycznie
  wysyłane (zobaczysz je jedynie w logach serwera).
- Ustaw unikalny, losowy `AUTH_SECRET`.
- Zmień hasła kont utworzonych przez seed (lub usuń konto demo rodzica).
- Na hostingu bez własnej kontroli nad procesem Node.js (np. panel typu
  "Node.js Selector" na home.pl) użyj `npm run start:passenger`
  (`node server.js`) zamiast `npm start` — patrz komentarz w `server.js`.
