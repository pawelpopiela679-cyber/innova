# Wdrożenie na home.pl (hosting z obsługą Node.js)

Ten przewodnik zakłada hosting home.pl z **pakietem obsługującym aplikacje
Node.js** (tzw. "Node.js Selector" — panel, w którym samodzielnie wskazujesz
wersję Node.js, katalog aplikacji i plik startowy). To mechanizm używany
niemal identycznie przez większość polskich hostingów współdzielonych
(cPanel / DirectAdmin / Plesk z modułem Node.js), więc poniższe kroki
powinny się zgadzać, choć **dokładne nazwy przycisków/zakładek w Panelu
klienta home.pl mogą się różnić** — jeśli czegoś nie znajdziesz, napisz do
supportu home.pl z pytaniem "jak uruchomić własną aplikację Node.js" i podaj
im tę stronę jako punkt odniesienia.

**Jeśli po zalogowaniu do Panelu klienta nie widzisz nigdzie opcji
"Aplikacje Node.js" / "Node.js Selector"** — Twój obecny pakiet hostingowy
prawdopodobnie tego nie obsługuje (sam "hosting WWW" na PHP+MySQL nie
wystarczy). Skontaktuj się z home.pl, żeby zmienić/dokupić pakiet z
obsługą Node.js, albo rozważ VPS home.pl (tam masz pełną kontrolę przez SSH).

## Krok 0: czego będziesz potrzebować

- Domena wskazująca na hosting home.pl.
- Pakiet hostingowy z obsługą Node.js (patrz wyżej), **Node.js w wersji 20
  lub nowszej**.
- Dostęp do plików (File Manager w Panelu klienta, albo FTP/SFTP).
- Baza danych **MySQL** utworzona w Panelu klienta home.pl (standardowo
  dostępna na każdym pakiecie) — zalecana zamiast domyślnego SQLite, bo jest
  objęta backupem home.pl i nie zależy od tego, czy katalog aplikacji
  przetrwa między restartami. Zapisz sobie: nazwę bazy, użytkownika, hasło,
  host (zwykle `localhost`).

## Krok 1: przełącz bazę danych z SQLite na MySQL

Na swoim komputerze (tam, gdzie masz kod), w pliku `prisma/schema.prisma`
zmień:

```prisma
datasource db {
  provider = "sqlite"
  url      = env("DATABASE_URL")
}
```

na:

```prisma
datasource db {
  provider = "mysql"
  url      = env("DATABASE_URL")
}
```

Usuń stare migracje (były wygenerowane pod SQLite, nie pasują do MySQL) i
wygeneruj nowe — w terminalu, w folderze projektu:

```
rmdir /s /q prisma\migrations        (Windows)
rm -rf prisma/migrations              (Mac/Linux)
```

Ustaw w swoim lokalnym `.env` tymczasowo dane do jakiejś testowej bazy MySQL
(albo od razu do docelowej na home.pl, jeśli masz już dostęp z zewnątrz —
home.pl zwykle wymaga włączenia zdalnego dostępu do MySQL w panelu), np.:

```
DATABASE_URL="mysql://UZYTKOWNIK:HASLO@HOST:3306/NAZWA_BAZY"
```

Wygeneruj migracje i sprawdź, że wszystko działa lokalnie:

```
npm run db:migrate
npm run db:seed
npm run build
```

Zacommituj zmiany (`prisma/schema.prisma` + nowy folder `prisma/migrations`)
do swojego repozytorium.

## Krok 2: wgraj pliki na serwer

Najprościej przez **File Manager** w Panelu klienta home.pl:

1. Spakuj cały projekt do pliku ZIP **bez** folderów `node_modules`,
   `.next` i `.git` (są duże i zostaną utworzone od nowa na serwerze).
2. W File Managerze wejdź do katalogu, w którym chcesz mieć aplikację
   (np. nowy folder poza `public_html`, panel Node.js sam podłączy go do
   domeny).
3. Wgraj ZIP i rozpakuj go tam (File Manager home.pl ma opcję "Rozpakuj").

Alternatywnie: jeśli home.pl daje dostęp SSH do Twojego pakietu, możesz
sklonować repozytorium bezpośrednio przez `git clone` — szybciej i łatwiej
o kolejne aktualizacje.

## Krok 3: utwórz aplikację Node.js w panelu

W Panelu klienta home.pl znajdź sekcję dot. **Aplikacji Node.js** (zwykle
w ustawieniach danego hostingu/domeny) i utwórz nową aplikację:

- **Wersja Node.js**: 20.x lub nowsza.
- **Katalog aplikacji (Application root)**: folder, do którego wgrałeś
  pliki w Kroku 2.
- **URL aplikacji**: Twoja domena (lub subdomena).
- **Plik startowy (Application startup file)**: `server.js`

  *(ten plik już jest w repozytorium — to mały serwer-wrapper, bo panele
  tego typu potrafią uruchamiać tylko pojedynczy plik JS, a nie polecenie
  `next start`; szczegóły w komentarzu na górze pliku).*

Po zapisaniu panel zwykle udostępnia **link do aktywacji środowiska** (coś
w stylu `source /home/TWOJA_NAZWA/nodevenv/ścieżka/20/bin/activate`) oraz
przycisk **"Uruchom NPM Install"** — to jest kluczowe, użyj go zamiast
zwykłego `npm install`, żeby zainstalować zależności we właściwym,
odizolowanym środowisku Node tego panelu.

## Krok 4: zmienne środowiskowe (`.env`)

Najprostszy sposób: w File Managerze, w katalogu aplikacji, utwórz plik
`.env.production` (Next.js wczytuje go automatycznie w trybie produkcyjnym
— nie trzeba nic dodatkowo konfigurować w kodzie). Skopiuj do niego zawartość
`.env.example` i uzupełnij prawdziwymi danymi:

```
DATABASE_URL="mysql://UZYTKOWNIK:HASLO@localhost:3306/NAZWA_BAZY"
AUTH_SECRET="<wygeneruj losowy: openssl rand -base64 48>"
SMTP_HOST="..."
SMTP_PORT="587"
SMTP_USER="..."
SMTP_PASS="..."
SMTP_FROM="INNOVA <kontakt@twojadomena.pl>"
STUDIO_NOTIFY_EMAIL="twoj-adres@twojadomena.pl"
SEED_ADMIN_EMAIL="admin@twojadomena.pl"
SEED_ADMIN_PASSWORD="<ustaw mocne hasło>"
```

Dane SMTP dostaniesz od home.pl (jeśli korzystasz z ich poczty) albo z
zewnętrznej usługi mailowej. Jeśli panel Node.js home.pl ma też własną
sekcję "Zmienne środowiskowe" — możesz dodatkowo wpisać tam te same
wartości, nie zaszkodzi, ale plik `.env.production` wystarczy.

**Nigdy nie commituj `.env.production` do gita** — wpisz go ręcznie tylko
na serwerze.

## Krok 5: zainstaluj zależności, zbuduj, uruchom migracje

Aktywuj środowisko Node tego panelu (komenda z Kroku 3) w terminalu SSH
albo we wbudowanym terminalu panelu, wejdź do katalogu aplikacji i wykonaj
po kolei:

```
npm ci                       # albo przycisk "Run NPM Install" w panelu
npx prisma migrate deploy    # zakłada tabele w bazie MySQL
npx prisma db seed           # doda 6 rodzajów zajęć, cennik, konto admina
npm run build                # zbuduje aplikację produkcyjnie
```

## Krok 6: uruchom / zrestartuj aplikację

Wróć do panelu Node.js i kliknij **"Uruchom ponownie" / "Restart"** przy
swojej aplikacji. Panel sam odpali `node server.js` z ustawionym przez
siebie portem.

## Krok 7: sprawdź, czy działa

Wejdź na swoją domenę w przeglądarce. Zaloguj się kontem admina (dane z
`SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` z Kroku 4) i sprawdź `/admin`.

**Koniecznie zmień hasła kont testowych** (admin, prowadzący, demo-rodzic z
`rodzic@example.com`) albo usuń konto demo-rodzica przez panel bazy danych —
opis kont jest w `README.md`.

## Aktualizacje w przyszłości

Gdy zmienisz coś w kodzie:

1. Wgraj nowe pliki (nadpisz przez File Manager/FTP albo `git pull`, jeśli
   masz SSH) — **nie nadpisuj** folderu `public/uploads/` (patrz niżej).
2. W terminalu: `npm ci` (jeśli zmieniły się zależności), `npx prisma
   migrate deploy` (jeśli zmienił się schemat bazy), `npm run build`.
3. Zrestartuj aplikację w panelu Node.js.

### Zdjęcia prowadzących (`public/uploads/`)

Zdjęcia dodawane w `/admin/prowadzacy` zapisują się bezpośrednio na dysku
serwera, w folderze `public/uploads/instructors/` — nie są częścią kodu z
git. Przy aktualizacji plików na serwerze uważaj, żeby nie skasować/nadpisać
tego folderu (np. przy rozpakowywaniu nowego ZIP-a wybierz opcję scalania,
nie zastępowania całego katalogu). Warto też uwzględnić ten folder w swoich
kopiach zapasowych, tak jak bazę danych.

## Rozwiązywanie problemów

- **Biała strona / błąd 503 po restarcie** — sprawdź logi aplikacji w
  panelu Node.js (zwykle zakładka "Logi" przy aplikacji). Najczęstsza
  przyczyna: brak `npm run build` przed uruchomieniem, albo błędny
  `DATABASE_URL`.
- **Błąd Prisma o brakującym silniku (`query engine`)** — schemat już ma
  ustawione dodatkowe `binaryTargets` pod typowe dystrybucje Linuksa
  używane na hostingu współdzielonym; jeśli mimo to wystąpi błąd, uruchom
  `npx prisma generate` bezpośrednio na serwerze (nie tylko lokalnie) po
  `npm ci`.
- **E-maile nie przychodzą** — sprawdź dane `SMTP_*` w `.env.production`;
  bez nich aplikacja "wysyła" e-maile tylko do logów (nic nie dotrze do
  skrzynki).
- **Panel nie ma opcji Node.js w ogóle** — Twój pakiet hostingowy jej nie
  obsługuje; napisz do supportu home.pl albo rozważ VPS.
