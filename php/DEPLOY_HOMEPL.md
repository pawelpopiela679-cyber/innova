# Wdrożenie na home.pl — zwykły hosting, BEZ Node.js

Ta instrukcja zakłada **dowolny pakiet hostingowy home.pl z obsługą PHP i
MySQL** — czyli praktycznie każdy pakiet "Hosting WWW", nawet najtańszy.
Nie potrzebujesz pakietu z Node.js, nie potrzebujesz SSH, nie potrzebujesz
niczego instalować w terminalu.

## Krok 0: czego będziesz potrzebować

- Domena wskazująca na hosting home.pl (już masz, skoro czytasz tę
  instrukcję).
- Dostęp do **File Managera** w Panelu klienta home.pl (albo FTP — dane
  znajdziesz w Panelu klienta, sekcja "FTP").
- Baza danych **MySQL** — zakładasz ją sama/sam w Panelu klienta (patrz
  Krok 1). Jest standardowo dostępna na każdym pakiecie hostingowym.

## Krok 1: załóż bazę danych MySQL

1. Zaloguj się do Panelu klienta home.pl.
2. Znajdź sekcję **"Bazy danych" / "MySQL"** (czasem pod "Hosting" →
   Twoja domena).
3. Utwórz nową bazę danych — zapisz sobie dokładnie:
   - nazwę bazy,
   - nazwę użytkownika,
   - hasło,
   - host (zwykle `localhost`).

Te dane wpiszesz za chwilę w Kroku 3.

## Krok 2: wgraj pliki na serwer

1. W tym repozytorium **potrzebujesz tylko zawartości folderu `php/`** —
   reszta repozytorium (Next.js) Cię teraz nie interesuje.
2. Spakuj zawartość folderu `php/` do ZIP-a (albo pobierz go z GitHuba jako
   ZIP i wypakuj tylko folder `php`).
3. W File Managerze home.pl wejdź do `public_html` (albo do podfolderu, jeśli
   strona ma tam być pod jakimś adresem, np. `public_html/zapisy`).
4. Wgraj ZIP-a i rozpakuj go **bezpośrednio w tym katalogu** (zawartość
   folderu `php/`, nie sam folder `php/` jako podkatalog) — czyli po
   rozpakowaniu w `public_html` powinny być widoczne pliki `index.php`,
   `config.php`, foldery `includes/`, `assets/`, itd., a nie
   `public_html/php/index.php`.

Alternatywnie, jeśli wolisz FTP: połącz się klientem FTP (np. FileZilla)
danymi z Panelu klienta i wgraj zawartość `php/` do `public_html`.

## Krok 3: skonfiguruj dane do bazy

1. W File Managerze, w katalogu gdzie wgrałeś pliki, znajdź plik
   `config.local.php.example`.
2. Zrób jego kopię i nazwij ją dokładnie: `config.local.php`
   (w File Managerze: zaznacz plik → "Kopiuj"/"Duplikuj", potem zmień nazwę).
3. Otwórz `config.local.php` do edycji (File Manager ma zwykle opcję
   "Edytuj" po kliknięciu prawym przyciskiem) i uzupełnij:

```php
define('DB_DRIVER', 'mysql');
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'TWOJA_NAZWA_BAZY');
define('DB_USER', 'TWOJ_UZYTKOWNIK_BAZY');
define('DB_PASS', 'TWOJE_HASLO_DO_BAZY');

define('APP_URL', 'https://twojadomena.pl');
define('AUTH_SECRET', 'wklej-tu-cokolwiek-dlugiego-i-losowego-np-40-znakow');
```

4. Uzupełnij też dane SMTP, żeby e-maile faktycznie się wysyłały (inaczej
   będą tylko zapisywane do pliku `storage/mail.log`, nikt ich nie dostanie):

```php
define('SMTP_HOST', 'poczta.home.pl');      // albo Twój własny serwer pocztowy
define('SMTP_PORT', 587);
define('SMTP_USER', 'kontakt@twojadomena.pl');
define('SMTP_PASS', 'haslo-do-tej-skrzynki');
define('SMTP_FROM_EMAIL', 'kontakt@twojadomena.pl');
define('SMTP_FROM_NAME', 'INNOVA');
define('STUDIO_NOTIFY_EMAIL', 'twoj-adres@twojadomena.pl');
```

   Dane SMTP dla skrzynki założonej w home.pl znajdziesz w Panelu klienta →
   Poczta → (Twoja skrzynka) → Konfiguracja programu pocztowego.

5. Zapisz plik.

**Ten plik (`config.local.php`) zawiera hasła — nigdy nie umieszczaj go w
publicznym repozytorium git.** Na serwerze jest dodatkowo zablokowany przez
`.htaccess`, więc nikt z zewnątrz go nie otworzy w przeglądarce.

## Krok 4: uruchom instalator

1. Wejdź w przeglądarce na: `https://twojadomena.pl/install.php`
2. Zobaczysz stronę z informacją, czy połączenie z bazą działa. Jeśli jest
   błąd — wróć do Kroku 3 i sprawdź dane w `config.local.php`.
3. Kliknij **"Zainstaluj / zaktualizuj bazę danych"**.
4. Zobaczysz listę utworzonych danych (6 rodzajów zajęć, terminy, konto
   admina) i dane logowania.

## Krok 5: zabezpiecz się po instalacji

Zrób to **od razu**, zanim ktokolwiek inny wejdzie na stronę:

1. Zaloguj się kontem `admin@innova-pracownia.pl` / `ZmienMnie123!`
   (albo tym, co ustawiłeś w `SEED_ADMIN_EMAIL`/`SEED_ADMIN_PASSWORD`).
2. Wejdź w **"Mój profil"** i zmień hasło (i jeśli chcesz, e-mail/nazwę) na
   własne.
3. Usuń plik `install.php` z serwera przez File Manager (żeby nikt inny nie
   mógł go ponownie uruchomić) — albo, jeśli wolisz go zostawić na
   przyszłość (np. do dodania nowej migracji), przynajmniej upewnij się, że
   `SEED_ADMIN_PASSWORD` w `config.local.php` jest już Twoim prawdziwym,
   bezpiecznym hasłem, bo każde ponowne uruchomienie instalatora
   nadpisuje tylko dane startowe, nie Twoje zmiany w profilu — ale i tak
   bezpieczniej usunąć plik.
4. W Panelu klienta home.pl zmień hasło do skrzynki `rodzic@example.com`
   (konto demo) albo usuń je w bazie przez phpMyAdmin (Panel klienta →
   Bazy danych → phpMyAdmin → tabela `users`).
5. **Załóż darmowy certyfikat SSL** (Panel klienta home.pl → SSL/Let's
   Encrypt) i odkomentuj blok wymuszający HTTPS w `.htaccess` (opisany
   wewnątrz pliku, na samym końcu) — bez tego dane logowania i dzieci
   podróżują niezaszyfrowane, a ciasteczko sesji nigdy nie dostanie flagi
   `secure`.

Dane logowania mają już wbudowaną ochronę przed brute-force (blokada na
15 minut po 5 nieudanych próbach z tego samego adresu IP albo na to samo
konto) oraz komplet nagłówków bezpieczeństwa HTTP w `.htaccess` — nic
dodatkowego nie musisz konfigurować.

## Krok 6: sprawdź, czy działa

Wejdź na swoją domenę — powinna wyświetlić się strona główna INNOVA.
Sprawdź kalendarz, spróbuj się zarejestrować jako rodzic, zgłoś dziecko na
zajęcia i sprawdź w `/admin-zapisy.php`, czy zgłoszenie tam jest.

## Prawdziwe logo

Wgraj plik z logo (najlepiej PNG z przezroczystym tłem) przez File Manager
jako:

```
logo.png
```

(w tym samym katalogu co `index.php`). Strona automatycznie zacznie go
pokazywać wszędzie zamiast odtworzonej w kodzie wersji.

## Aktualizacje w przyszłości

Gdy coś zmienisz w kodzie (albo dostaniesz nową wersję ode mnie):

1. Wgraj nowe pliki `.php` (nadpisz stare przez File Manager/FTP).
2. **Nie nadpisuj** pliku `config.local.php` (Twoje dane logowania) ani
   folderu `uploads/instructors/` (zdjęcia prowadzących) — przy
   rozpakowywaniu nowego ZIP-a wybierz opcję scalania/pomijania istniejących
   plików, jeśli File Manager o to pyta.
3. Jeśli coś zmieniło się w bazie danych, otwórz ponownie `install.php` —
   bezpiecznie dopisze tylko brakujące tabele/kolumny.

## Rozwiązywanie problemów

- **Biała strona / błąd 500** — w File Managerze sprawdź, czy istnieje
  `config.local.php` (jeśli nie, zobaczysz czytelny komunikat, co zrobić).
  Jeśli plik istnieje, sprawdź logi błędów PHP w Panelu klienta home.pl
  (zwykle sekcja "Logi błędów" przy danej domenie).
- **Błąd połączenia z bazą na `install.php`** — sprawdź dokładnie
  `DB_HOST`/`DB_NAME`/`DB_USER`/`DB_PASS` w `config.local.php`; najczęstszy
  błąd to literówka w nazwie użytkownika (home.pl zwykle dodaje przedrostek
  do nazwy użytkownika bazy, np. `login_nazwabazy`).
- **E-maile nie przychodzą** — sprawdź dane `SMTP_*`; bez nich e-maile
  lądują tylko w pliku `storage/mail.log` na serwerze (możesz go otworzyć
  przez File Manager, żeby zobaczyć treść, która "powinna" była pójść).
- **Zdjęcia prowadzących się nie zapisują** — sprawdź uprawnienia zapisu
  (CHMOD) folderu `uploads/instructors/` w File Managerze — powinno być
  755 lub 775.
- **Strona działa, ale bez stylów (brzydka, czarno-biała)** — sprawdź, czy
  folder `assets/` wgrał się poprawnie razem z resztą plików.
