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

## Płatności online (Tpay)

Rodzice mogą płacić online kartą/BLIK-iem/przelewem, a organizacje mogą
włączyć automatyczne pobieranie za kolejne zajęcia zapisaną kartą. Cała
logika jest gotowa w kodzie (`includes/tpay.php`, `platnosc.php`,
`webhook-tpay.php`, `cron-platnosci-cykliczne.php`) — tu tylko podłączasz
prawdziwe konto Tpay. Dopóki tego nie zrobisz, appka działa normalnie,
tylko bez przycisku „Zapłać online” (organizacje rozliczają się tradycyjnie
— przelew/gotówka + ręczne „Oznacz jako opłacone”).

1. **Załóż konto na [tpay.com](https://tpay.com/dla-developera)** — najpierw
   w trybie **sandbox** (środowisko testowe, fałszywe pieniądze), żeby
   przetestować cały przepływ przed przyjęciem pierwszej prawdziwej wpłaty.
2. W panelu Tpay: **Ustawienia → Integracja → OpenAPI** znajdziesz
   `Client ID` i `Client Secret` — wklej je do `config.local.php` jako
   `TPAY_CLIENT_ID` / `TPAY_CLIENT_SECRET`. `TPAY_MERCHANT_ID` to numer
   Twojej skrzynki (POS ID), widoczny przy danych konta.
3. W panelu Tpay: **Ustawienia → Powiadomienia** ustaw adres webhooka na:
   ```
   https://twojadomena.pl/webhook-tpay.php
   ```
   (podmień na swoją prawdziwą domenę/ścieżkę instalacji). To jedyny adres,
   na który Tpay wysyła potwierdzenia płatności — bez tego appka nigdy nie
   dowie się, że ktoś zapłacił.
4. W `config.local.php` ustaw `TPAY_ENV = 'sandbox'` i **`TPAY_SIMULATE =
   false`** (wyłącz tryb symulacji — od teraz chcesz testować z
   PRAWDZIWYM, choć testowym, kontem Tpay, nie z fałszywymi danymi).
5. Zrób próbną płatność sandboxową całą ścieżką: zaloguj się jako rodzic
   testowej organizacji → „Moje zapisy” → „Zapłać online” → dokończ
   płatność testową kartą sandbox Tpay (numery testowych kart są w
   dokumentacji Tpay) → sprawdź, że po powrocie status zmienia się na
   „Opłacone” (może to potrwać kilka-kilkanaście sekund — czekasz na
   webhook, patrz komentarz w `platnosc-powrot.php`).
6. Gdy sandbox działa poprawnie: załóż konto **produkcyjne** w Tpay,
   podmień `TPAY_CLIENT_ID`/`TPAY_CLIENT_SECRET`/`TPAY_MERCHANT_ID` na
   produkcyjne, ustaw `TPAY_ENV = 'production'`. `TPAY_SIMULATE` musi
   zostać `false` — appka i tak krzyczy w logu PHP (`error_log`), gdyby
   ktoś przypadkiem zostawił oba naraz włączone.

⚠ **Zanim przyjmiesz pierwszą prawdziwą płatność**, przeczytaj komentarze
oznaczone `⚠ ZWERYFIKUJ` w `includes/tpay.php` (adres sandboxa, dokładny
kształt pól przy tokenizacji/obciążaniu tokenu, nazwa pola z numerem
transakcji w webhooku) i porównaj je z aktualną dokumentacją
[docs-api.tpay.com](https://docs-api.tpay.com/pl/) — API bywa aktualizowane,
a ten kod odtwarza jego kształt najlepiej, jak było to możliwe bez
prawdziwego konta w chwili pisania.

### Płatności cykliczne (cron)

Jeśli chcesz, żeby appka automatycznie pobierała opłaty za kolejne zajęcia
zapisaną kartą (rodzic musi się na to zgodzić — checkbox „zapamiętaj kartę”
przy płatności, potem może to wyłączyć w „Moje płatności”), dodaj zadanie
cron w home.pl:

1. Panel klienta home.pl → **Zaawansowane → Harmonogram zadań (CRON)**.
2. Dodaj nowe zadanie, uruchamiane **raz dziennie** (np. o 8:00), z
   poleceniem (podmień ścieżkę na swoją rzeczywistą — home.pl pokazuje ją
   w panelu przy Twoim koncie):
   ```
   php /home/users/TWOJ_LOGIN/domains/twojadomena.pl/public_html/system/cron-platnosci-cykliczne.php
   ```
3. Jeśli Twój pakiet home.pl nie pozwala na uruchamianie skryptów PHP z
   linii poleceń w harmonogramie (rzadkie, ale się zdarza na starszych
   pakietach) — alternatywa to zadanie cron wywołujące adres URL (np.
   `wget -q -O /dev/null "https://twojadomena.pl/cron-platnosci-cykliczne.php?secret=..."`).
   W takim wypadku **koniecznie** ustaw najpierw `CRON_SECRET` w
   `config.local.php` na długi, losowy ciąg znaków i użyj go w `?secret=...`
   — bez tego skrypt odpowie błędem 403 (celowe zabezpieczenie, patrz
   komentarz na górze `cron-platnosci-cykliczne.php` — ten skrypt pobiera
   prawdziwe pieniądze, nie może być dostępny publicznie bez sekretu).
4. Sprawdź działanie: po pierwszym uruchomieniu zajrzyj do logu zadania w
   panelu home.pl (albo dodaj na końcu polecenia przekierowanie do pliku,
   np. `>> /home/users/TWOJ_LOGIN/cron-platnosci.log 2>&1`) — skrypt loguje
   każdą próbę (ile zainicjował, ile pominął brakiem zgody/karty, ile się
   nie udało).

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
4. **Uwaga — to inna płatność niż Tpay opisane wyżej.** Integracja Tpay
   obsługuje płatności RODZICÓW za zajęcia w danej organizacji. Nie ma
   dziś analogicznej bramki dla ABONAMENTU, który organizacje płacą Tobie
   za korzystanie z InnovaGo — zmiana planu w `/abonament.php` jest
   natychmiastowa i nie wymaga potwierdzenia płatności (patrz
   README_PHP.md, sekcja "czego świadomie brakuje"). Zdecyduj, jak na
   razie rozliczasz się z organizacjami (ręcznie/przelewem), dopóki nie
   dobudujesz tu osobnej bramki.
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
