# Wdrożenie InnovaGo na home.pl

Te same ogólne kroki co dla INNOVA (`../../php/DEPLOY_HOMEPL.md`) — hosting
home.pl **z pakietem PHP + MySQL** (zwykły "hosting WWW", żadnego Node.js
Selectora tu nie trzeba, w odróżnieniu od wersji Next.js w roocie repo).
Poniżej skrócona wersja z różnicami specyficznymi dla InnovaGo.

## Krok 0: czego potrzebujesz

- Domena (albo subdomena, np. `system.twojadomena.pl`) wskazująca na hosting home.pl.
- Zwykły hosting współdzielony PHP 8.1+ (home.pl ma to na każdym pakiecie WWW).
- Baza danych **MySQL** założona w Panelu klienta home.pl (nazwa, użytkownik, hasło, host — zwykle `localhost`).

## Krok 1: wgraj pliki

Wgraj **całą zawartość folderu `innovago/php/`** (nie cały folder `innovago`,
tylko jego podfolder `php/`) do katalogu, w którym ma stanąć aplikacja —
przez File Manager (ZIP + rozpakuj) albo `git clone`/`git pull`, jeśli masz
SSH. Struktura na serwerze musi wyglądać tak, że `install.php` leży
bezpośrednio w katalogu głównym aplikacji (np. `public_html/system/install.php`).

## Krok 2: config.local.php

```
cp config.local.php.example config.local.php
```

Uzupełnij dane MySQL z Panelu klienta home.pl, `APP_URL` (adres, pod którym
stanie aplikacja), losowy `AUTH_SECRET`. Zostaw `SMTP_*` i `SMS_API_TOKEN`
puste na start — dorzucisz je, gdy będziesz gotowy wysyłać prawdziwe
e-maile/SMS-y (patrz README_PHP.md).

**Zmień od razu `SEED_SUPERADMIN_EMAIL` / `SEED_SUPERADMIN_PASSWORD`** na
własne dane — to konto, którym będziesz zarządzał całą platformą.

## Krok 3: zainstaluj

Wejdź w przeglądarce na `https://twojadomena.pl/install.php` i kliknij
„Zainstaluj”. Utworzy to tabele oraz:

- Twoje konto super-admina,
- 3 plany subskrypcji (Start/Pro/Biznes — ceny i limity zmienisz później w
  `/superadmin-plany.php`),
- jedną demo-organizację z przykładowymi danymi (usuń ją / zmień jej hasła
  przed pokazaniem systemu prawdziwym klientom — patrz krok 5).

## Krok 4: sprawdź, czy działa

Zaloguj się kontem super-admina na `/logowanie.php` → powinieneś trafić na
`/superadmin.php` i zobaczyć listę organizacji (na start: tylko demo).
Załóż testową organizację przez `/rejestracja-organizacji.php` (w innej
przeglądarce/oknie incognito, żeby nie wylogować się z konta super-admina)
i sprawdź, że panel organizacji działa.

## Krok 5: przed pokazaniem prawdziwym klientom

1. Usuń plik `install.php` z serwera (albo zablokuj go hasłem) — każdy, kto
   go znajdzie, może nadpisać dane startowe.
2. Usuń demo-organizację („Demo Szkółka Rozwoju”) w `/superadmin.php`
   (ustaw status `CANCELED` — usuwanie organizacji z bazy nie ma dziś
   przycisku w UI, zrób to przez `npx prisma studio`-odpowiednik: panel
   bazy danych home.pl / phpMyAdmin, `DELETE FROM organizations WHERE slug
   = 'demo-szkola'` — kasowanie jest kaskadowe dzięki `ON DELETE CASCADE`).
3. Skonfiguruj prawdziwe dane SMTP w `config.local.php`, inaczej e-maile do
   Twoich klientów i ich rodziców nie będą faktycznie wysyłane.
4. Zdecyduj, jak będziesz pobierał opłaty za abonament od organizacji, dopóki
   nie podepniesz bramki płatności — dziś zmiana planu w `/abonament.php`
   jest natychmiastowa i nie wymaga potwierdzenia płatności (patrz
   README_PHP.md, sekcja "czego świadomie brakuje").
5. **Załóż darmowy certyfikat SSL** (Panel klienta home.pl → SSL/Let's
   Encrypt) i odkomentuj blok wymuszający HTTPS w `.htaccess` (opisany
   wewnątrz pliku, na samym końcu) — to trzyma dane logowania i dane dzieci
   Twoich klientów zaszyfrowane w tranzycie, a ciasteczko sesji dostaje
   flagę `secure`. **Szczególnie ważne przy danych dzieci** (RODO).

Logowanie ma już wbudowaną ochronę przed brute-force (blokada na 15 minut
po 5 nieudanych próbach z tego samego adresu IP albo na to samo konto —
niezależnie dla każdej organizacji) oraz komplet nagłówków bezpieczeństwa
HTTP w `.htaccess` — nic dodatkowego nie musisz konfigurować.

## Aktualizacje w przyszłości

Jak w INNOVA: wgraj nowe pliki (nie nadpisuj `uploads/`), uruchom ponownie
`install.php` jeśli zmienił się schemat bazy (bezpieczne — `CREATE TABLE IF
NOT EXISTS`), i gotowe — żadnego builda, żadnego restartu procesu Node.js.

## Rozwiązywanie problemów

Patrz sekcja „Rozwiązywanie problemów” w `../../php/DEPLOY_HOMEPL.md` —
dotyczy identycznie tej wersji (białe strony, błędy Prisma/PDO, e-maile w
logu zamiast skrzynki).
