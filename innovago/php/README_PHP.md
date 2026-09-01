# InnovaGo — wersja PHP (multi-tenant SaaS)

Druga wersja pomysłu z `../../php` (INNOVA), tym razem zbudowana jako
**platforma SaaS**: jedna instalacja obsługuje **wiele organizacji**
(szkółek/klubów), każda ze swoim kalendarzem, zapisami, prowadzącymi i
kolorystyką, sprzedawana w modelu **abonamentu miesięcznego** (plany Start /
Pro / Biznes). Czysty PHP + MySQL, bez Composera i bez SSH — działa na
zwykłym hostingu współdzielonym takim jak home.pl, dokładnie jak `../../php`.

## Czym różni się od INNOVA (`../../php`)

| | INNOVA | InnovaGo |
|---|---|---|
| Ile organizacji obsługuje jedna instalacja | 1 (jedna pracownia) | Wiele (każda z osobnym adminem, kalendarzem, danymi) |
| Model sprzedaży | brak — własna appka jednej firmy | Abonament miesięczny, plany z limitami |
| Kto zarządza czym | właściciel pracowni + prowadzący | **Ty (super-admin)** zarządzasz organizacjami/planami; **właściciel organizacji** zarządza swoją szkółką |
| Nowe funkcje | — | odrabianie zajęć po nieobecności, status płatności per zapis, godziny i szacowane wynagrodzenia prowadzących, masowa komunikacja e-mail/SMS |
| Wygląd | oliwkowo-beżowy, Fredoka/Caveat | ten sam branding co INNOVA (oliwka/beż/koral/musztarda, Fredoka/Quicksand/Nunito) + animacje (kalendarz, liczniki, karty) |

## Role

- **SUPER_ADMIN** — Ty, właściciel platformy InnovaGo. Widzi wszystkie
  organizacje, zmienia ich plan/status, edytuje ceny planów (`/superadmin*.php`).
- **ORG_ADMIN** — właściciel danej organizacji (szkółki/klubu). Wszystko, co
  prowadzący, plus: dodaje/usuwa prowadzących, wygląd, komunikacja, godziny
  i wynagrodzenia, abonament.
- **INSTRUCTOR** — prowadzący w danej organizacji: kalendarz, zgłoszenia,
  zajęcia.
- **PARENT** — rodzic zarejestrowany do KONKRETNEJ organizacji (przez link
  `rejestracja.php?org=slug-organizacji`, który każda organizacja dostaje po
  założeniu konta).

## Szybki start (lokalnie, do testów)

Wymagany PHP 8.1+ (bez żadnych rozszerzeń poza wbudowanymi — `pdo_sqlite`
albo `pdo_mysql`, `mbstring`, `fileinfo`).

```bash
cd innovago/php
cp config.local.php.example config.local.php
# W config.local.php zostaw DB_DRIVER=sqlite do szybkich testów lokalnych.
php -S localhost:8099
```

Wejdź na `http://localhost:8099/install.php` i kliknij „Zainstaluj”. Zaloguj
się kontem super-admina (`SEED_SUPERADMIN_EMAIL` / `SEED_SUPERADMIN_PASSWORD`
z `config.local.php`) na `http://localhost:8099/logowanie.php`, albo od razu
kontem demo-organizacji (`SEED_DEMO_ORG_ADMIN_EMAIL` /
`SEED_DEMO_ORG_ADMIN_PASSWORD`) — zobaczysz gotowe dane demo (zajęcia,
zgłoszenie nieopłacone, zgłoszenie z nieobecnością do odrobienia).

## Wdrożenie na home.pl

Patrz [`DEPLOY_HOMEPL.md`](./DEPLOY_HOMEPL.md) — te same kroki co dla INNOVA
(`../../php/DEPLOY_HOMEPL.md`), z jedną różnicą: po instalacji NIE zakładasz
danych jednej pracowni, tylko konto super-admina, a każdy Twój klient
zakłada SWOJĄ organizację samodzielnie przez `/rejestracja-organizacji.php`
(albo Ty zakładasz ją za niego w `/superadmin.php`).

## Struktura

```
config.php / config.local.php.example   konfiguracja (patrz wyżej)
install.php                              instalator (tabele + dane startowe)
includes/schema.php                      CREATE TABLE — organizations, subscription_plans,
                                          users (z org_id), class_types, class_sessions,
                                          enrollments (payment_status, attendance_status,
                                          rescheduled_to_enrollment_id), org_settings
includes/tenant.php                      current_org(), require_org(), limity planu
includes/enrollment.php                  potwierdzanie, lista rezerwowa, ODRABIANIE ZAJĘĆ,
                                          status płatności
includes/sms.php                         punkt integracji z bramką SMS (patrz komentarz w pliku)
index.php, cennik.php                    strona marketingowa platformy + cennik planów
rejestracja-organizacji.php              samoobsługowa rejestracja NOWEGO klienta SaaS-a
rejestracja.php?org=slug                 rejestracja RODZICA do konkretnej organizacji
superadmin*.php                          panel Twój — organizacje, plany
admin.php, kalendarz.php, zapisy.php,
zajecia.php, prowadzacy.php,
komunikacja.php, godziny.php,
wyglad.php, abonament.php                panel organizacji (ORG_ADMIN/INSTRUCTOR)
panel-rodzic.php, panel-dzieci.php,
panel-zapisy.php                         panel rodzica — w tym odrabianie zajęć
```

## Czego świadomie brakuje w wersji 1 (jasno nazwane, nie ukryte)

- **Płatności online** (Przelewy24/PayU za zajęcia, kartą za abonament) —
  status płatności jest dziś ręczny (rodzic płaci przelewem/gotówką, admin
  klika „Oznacz jako opłacone”). To najbardziej oczywisty następny krok.
- **SMS** — kod jest gotowy (`includes/sms.php`), ale wymaga Twojego konta
  u dostawcy (SMSAPI.pl i podobne) i wklejenia klucza API.
- **Własne subdomeny per organizacja** (np. `demo-szkola.innovago.pl`) —
  dziś wszystkie organizacje żyją pod jedną domeną, rozróżnione linkiem
  rejestracyjnym (`rejestracja.php?org=slug`) i kontem logowania.
- Funkcje, które INNOVA ma, a InnovaGo v1 celowo pominęła, żeby dowieźć
  rdzeń SaaS-a: własne podstrony (Regulamin/FAQ) i galeria „Poznaj nas”.
  Kod motywu (`includes/theme.php`) już jest wielo-najemczy, więc dodanie
  ich później to kwestia skopiowania odpowiednich plików z `../../php`.
