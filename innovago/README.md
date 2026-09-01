# InnovaGo — drugi projekt w tym repo

Autorska platforma **SaaS multi-tenant** dla szkółek i klubów zajęć dla
dzieci — inspirowana niszą [ActiveNow](https://activenow.io/) (płatny zapis
i zarządzanie szkołą), ale zbudowana od zera, z innym designem i kilkoma
funkcjami, których w tej niszy zwykle brakuje w podstawowym pakiecie.

Technicznie to osobny projekt w tym samym repozytorium (patrz Twoje pytanie o
"2 projekty w jednym repo": tak, tak to wygląda w praktyce) — ale **jeden
branding** z `INNOVA` (Next.js w roocie repo + `../php`): ta sama paleta
kolorów (oliwka/beż/koral/musztarda), te same fonty (Fredoka/Quicksand/
Nunito), ten sam rodzaj logotypu. InnovaGo to produkt marki Innova — appka
stworzona przez pracownię, dla innych pracowni.

## Dwa foldery, dwa cele

- **[`preview/`](./preview)** — statyczny HTML/CSS/JS, **zero zależności
  serwerowych**. Pobierz i otwórz `preview/index.html` prosto z dysku
  (dwuklik) — działa offline, dane są na sztywno wpisane w JS jako demo.
  Zawiera 5 stron: strona główna z cennikiem, panel organizacji, kalendarz
  (klikalny), panel rodzica (z **żywą demonstracją odrabiania zajęć** —
  kliknij „Zgłoś nieobecność”, potem wybierz termin), logowanie.
- **[`php/`](./php)** — pełna, działająca aplikacja: PHP 8.1+ + MySQL (albo
  SQLite lokalnie), bez Composera i bez SSH — dokładnie jak `../php`
  (INNOVA), więc gotowa pod hosting typu home.pl. To jest to, co faktycznie
  stawiasz na serwer. Szczegóły: [`php/README_PHP.md`](./php/README_PHP.md),
  wdrożenie krok po kroku: [`php/DEPLOY_HOMEPL.md`](./php/DEPLOY_HOMEPL.md).

## Co odróżnia InnovaGo od INNOVA

INNOVA to appka **jednej pracowni**. InnovaGo to **produkt, który
sprzedajesz** wielu właścicielom szkółek w modelu subskrypcji:

- **Wiele organizacji na jednej instalacji** — każda ze swoim adminem,
  kalendarzem, prowadzącymi, kolorystyką; dane w pełni odizolowane.
- **Ty jesteś super-adminem platformy** — widzisz wszystkie organizacje,
  ich plan i status, MRR, zmieniasz ceny planów.
- **Plany subskrypcji z limitami** (liczba prowadzących / zapisanych dzieci)
  egzekwowanymi w aplikacji.
- **Samoobsługowa rejestracja** nowej organizacji (14-dniowy trial).
- Nowe funkcje, których nie ma INNOVA: **odrabianie zajęć** po zgłoszonej
  nieobecności, **status płatności** per zapis, **godziny i szacowane
  wynagrodzenia** prowadzących, **masowa komunikacja** e-mail/SMS.
- Animowany interfejs w tej samej palecie i tych samych fontach co INNOVA —
  patrz `preview/` dla szybkiego podglądu bez instalowania czegokolwiek.

## Czego świadomie zabrakło w wersji 1

Płatności online (Przelewy24/PayU) i prawdziwa integracja SMS wymagają
Twojego konta u dostawcy — kod jest przygotowany pod te integracje
(`php/includes/sms.php`, status płatności w `enrollments`), ale nie mogłem
podłączyć realnych kluczy API, bo ich nie mam. Pełna lista i uzasadnienie:
sekcja „Czego świadomie brakuje” w [`php/README_PHP.md`](./php/README_PHP.md).
