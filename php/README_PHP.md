# INNOVA — wersja PHP + MySQL (do home.pl bez Node.js)

To jest **druga, w pełni samodzielna wersja** tej samej aplikacji, napisana w
czystym PHP + MySQL zamiast Next.js/Node.js — specjalnie po to, żeby dało się
ją uruchomić na **zwykłym hostingu współdzielonym home.pl, bez pakietu
Node.js Selector**. Wygląd (kolory, czcionki, treść z ulotki) i wszystkie
funkcje są takie same jak w wersji Next.js w głównym folderze repozytorium —
zmienił się tylko silnik pod spodem.

**Zero zależności do instalowania**: żadnego Composera, żadnego `npm
install`, żadnego SSH. Wystarczy zwykły PHP 8+ i baza MySQL — to jest
domyślny zestaw na każdym, nawet najtańszym pakiecie hostingowym home.pl.

## Funkcje (identyczne jak w wersji Next.js)

- Konta rodziców, kalendarz zajęć (dzień/tydzień/miesiąc) z opisem i wolnymi
  miejscami.
- Zgłoszenia wymagające potwierdzenia (status „oczekujące” → pracownia
  potwierdza/przypisuje inną grupę/przenosi na listę rezerwową/odrzuca).
- Powiadomienie e-mail dla pracowni przy każdym zgłoszeniu (z wiekiem
  dziecka).
- Grupy z twardym limitem 10 dzieci.
- Każdy prowadzący dodaje własny grafik (cotygodniowe cykle).
- Master admin (właściciel pracowni): zakłada/edytuje/usuwa konta
  prowadzących (ze zdjęciem), zmienia kolorystykę całej strony na żywo,
  dodaje własne podstrony (np. „Regulamin”).
- Strona „Poznaj nas” generowana automatycznie z kont prowadzących.
- Każdy pracownik (admin i prowadzący) sam edytuje swój profil.

## Struktura

```
php/
  index.php, zajecia.php, kalendarz.php, poznaj-nas.php, strona.php   strony publiczne
  logowanie.php, rejestracja.php, wyloguj.php                         logowanie
  panel*.php                                                          panel rodzica
  admin*.php                                                          panel prowadzącego/admina
  install.php                                                         instalator (tabele + dane startowe)
  config.php, config.local.php.example                                konfiguracja (skopiuj .example → config.local.php)
  includes/                                                           wspólny kod PHP (baza, sesje, e-mail, itd.)
  assets/style.css                                                    cała stylistyka (paleta oliwka/beż/róż)
  uploads/instructors/                                                zdjęcia prowadzących (zapisywane na dysku)
```

Brak frameworka — każda strona to jeden plik `.php`, prosty do znalezienia i
edycji nawet bez znajomości PHP w stopniu eksperckim.

## Szybki start lokalnie (testowanie bez MySQL)

Wymagany PHP 8.1+ (sprawdzisz: `php -v`).

```bash
cd php
cp config.local.php.example config.local.php
```

W świeżo skopiowanym `config.local.php` zmień jedną linijkę na tryb
testowy bez zakładania bazy MySQL:

```php
define('DB_DRIVER', 'sqlite');
```

Uruchom wbudowany serwer PHP:

```bash
php -S localhost:8080
```

Wejdź na `http://localhost:8080/install.php` i kliknij „Zainstaluj”. Potem
`http://localhost:8080/` — gotowe, ze wszystkimi danymi startowymi (patrz
tabela kont niżej).

## Wdrożenie na home.pl (produkcja, MySQL)

**Pełna instrukcja krok po kroku: [`DEPLOY_HOMEPL.md`](./DEPLOY_HOMEPL.md)**.
W skrócie: wgrywasz folder `php/` przez File Manager/FTP, zakładasz bazę
MySQL w Panelu klienta home.pl, uzupełniasz `config.local.php` prawdziwymi
danymi i otwierasz `install.php` w przeglądarce raz — instalator sam tworzy
tabele i dane startowe. Bez SSH, bez terminala, bez Node.js.

## Konta utworzone przez install.php

| Rola | E-mail | Hasło |
| --- | --- | --- |
| Administrator / właściciel pracowni | `admin@innova-pracownia.pl` (albo `SEED_ADMIN_EMAIL` z configu) | `ZmienMnie123!` (albo `SEED_ADMIN_PASSWORD`) |
| Prowadzący (6 kont) | np. `marek@innova-pracownia.pl` | `Prowadzacy123!` |
| Przykładowy rodzic | `rodzic@example.com` | `Haslo123!` |

**Koniecznie zmień te hasła (w „Mój profil”) i usuń/zmień konto demo rodzica
przed udostępnieniem strony prawdziwym klientom.**

## Prawdziwe logo

Wgraj swój plik jako `php/logo.png` (przez File Manager) — strona
automatycznie zacznie go pokazywać wszędzie zamiast odtworzonego w CSS
wordmarku. Bez tego pliku strona po cichu pokazuje wersję odtworzoną w
kodzie.

## Bezpieczeństwo

- `config.local.php` (Twoje hasła do bazy) nigdy nie trafia do gita — jest
  w `.gitignore`. Na serwerze dodatkowo blokuje go `.htaccess`.
- Folder `includes/` jest zablokowany przez `.htaccess` — nie da się go
  otworzyć wprost w przeglądarce.
- Folder `uploads/` blokuje wykonywanie plików PHP (na wypadek podrzucenia
  złośliwego pliku pod fałszywym rozszerzeniem obrazka).
- Wszystkie formularze mają token CSRF.
- Hasła są hashowane (`password_hash`, bcrypt/Argon2 zależnie od PHP).
- **Po instalacji usuń `install.php` z serwera** (albo upewnij się, że
  hasło admina w `config.local.php` jest bezpieczne — patrz DEPLOY_HOMEPL.md).

## Różnice względem wersji Next.js (świadome uproszczenia)

- Bez frameworka JS — każde kliknięcie w formularz to pełne przeładowanie
  strony (klasyczny PHP), nie ma płynnych przejść SPA. Działa identycznie
  na każdej przeglądarce, nawet bardzo starej.
- Sesje logowania: natywne sesje PHP (plik/pamięć po stronie serwera)
  zamiast JWT w ciasteczku — prostsze i równie bezpieczne dla tego typu
  aplikacji.
- E-mail wysyłany własnym, minimalnym klientem SMTP (bez zewnętrznych
  bibliotek) — działa z każdym serwerem SMTP, także pocztą home.pl.
