<?php
/**
 * Dane startowe — dokładny odpowiednik prisma/seed.ts: konto master admina,
 * 6 prowadzących, 6 rodzajów zajęć z realnymi grupami wiekowymi i cennikiem,
 * cotygodniowe terminy od SEMESTER_START, jednorazowy "Dzień otwarty" oraz
 * przykładowy rodzic/dziecko/zapis. Bezpieczne do wielokrotnego uruchomienia
 * (upsert po adresie e-mail / kluczu / tytule+terminie).
 */

/**
 * Zakłada konto, jeśli nie istnieje ($createData). Jeśli konto JUŻ istnieje,
 * aktualizuje TYLKO pola podane w $updateData (domyślnie żadne) — dzięki
 * temu ponowne uruchomienie seeda (przy aktualizacji grafiku) nigdy nie
 * cofa zmian, które admin/prowadzący wprowadzili sami przez panel (własny
 * profil, edycja prowadzącego) — dokładnie tak jak działało to w
 * pierwotnej wersji Next.js (prisma upsert z pustym/częściowym `update`).
 */
function seed_upsert_user(PDO $pdo, string $email, array $createData, array $updateData = []): int
{
    $existing = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $existing->execute([$email]);
    $row = $existing->fetch();
    if ($row) {
        if ($updateData) {
            $sets = [];
            $values = [];
            foreach ($updateData as $col => $val) {
                $sets[] = "$col = ?";
                $values[] = $val;
            }
            $values[] = (int) $row['id'];
            $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
        }
        return (int) $row['id'];
    }
    $createData['email'] = $email;
    $cols = array_keys($createData);
    $stmt = $pdo->prepare('INSERT INTO users (' . implode(',', $cols) . ') VALUES (' . in_placeholders($cols) . ')');
    $stmt->execute(array_values($createData));
    return db_last_id($pdo);
}

/**
 * Zakłada grupę (class_groups), jeśli nie istnieje jeszcze grupa o takiej
 * nazwie w tym rodzaju zajęć — w przeciwnym razie aktualizuje dzień/godzinę/
 * prowadzącego/limit (na wypadek poprawek w grafiku), ale NIE rusza listy
 * przypisanych do niej dzieci (te żyją w enrollments.group_id, osobno).
 */
function seed_upsert_group(PDO $pdo, int $classTypeId, string $name, array $data): int
{
    $existing = $pdo->prepare('SELECT id FROM class_groups WHERE class_type_id = ? AND name = ?');
    $existing->execute([$classTypeId, $name]);
    $row = $existing->fetch();
    if ($row) {
        $pdo->prepare('UPDATE class_groups SET instructor_id=?, instructor_name=?, day_of_week=?, start_time=?, end_time=?, capacity=?, meeting_url=? WHERE id=?')
            ->execute([
                $data['instructor_id'], $data['instructor_name'], $data['day_of_week'],
                $data['start_time'], $data['end_time'], $data['capacity'], $data['meeting_url'], $row['id'],
            ]);
        return (int) $row['id'];
    }
    $pdo->prepare('INSERT INTO class_groups (class_type_id, name, instructor_id, instructor_name, day_of_week, start_time, end_time, capacity, meeting_url) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([
            $classTypeId, $name, $data['instructor_id'], $data['instructor_name'], $data['day_of_week'],
            $data['start_time'], $data['end_time'], $data['capacity'], $data['meeting_url'],
        ]);
    return db_last_id($pdo);
}

/** @return array{0:int,1:bool} [id klasy, czy dopiero co utworzona] */
function seed_upsert_class_type(PDO $pdo, string $key, array $data): array
{
    $existing = $pdo->prepare('SELECT id FROM class_types WHERE key_name = ?');
    $existing->execute([$key]);
    $row = $existing->fetch();
    if ($row) {
        $pdo->prepare('UPDATE class_types SET name=?, description=?, color=?, age_min=?, age_max=? WHERE id=?')
            ->execute([$data['name'], $data['description'], $data['color'], $data['age_min'], $data['age_max'], $row['id']]);
        return [(int) $row['id'], false];
    }
    $pdo->prepare('INSERT INTO class_types (key_name, name, description, color, age_min, age_max) VALUES (?,?,?,?,?,?)')
        ->execute([$key, $data['name'], $data['description'], $data['color'], $data['age_min'], $data['age_max']]);
    return [db_last_id($pdo), true];
}

/** @return string[] log linijek do pokazania na stronie install.php */
function run_seed(): array
{
    $pdo = db();
    $log = [];

    // --- Konto właściciela pracowni (master admin) ---
    // Bez $updateData: jeśli konto już istnieje, seed NIE dotyka imienia ani
    // hasła — nie cofa zmian wprowadzonych przez admina w "Mój profil".
    $adminId = seed_upsert_user($pdo, SEED_ADMIN_EMAIL, [
        'name' => 'Właściciel Pracowni',
        'role' => 'ADMIN',
        'password_hash' => hash_password(SEED_ADMIN_PASSWORD),
    ]);
    $log[] = 'Admin: ' . SEED_ADMIN_EMAIL . ' / hasło startowe: ' . SEED_ADMIN_PASSWORD . ' (tylko przy pierwszej instalacji — późniejsze zmiany w „Mój profil” nie są nadpisywane)';

    // --- Prowadzący ---
    $instructorDefs = [
        ['email' => 'ola@innova-pracownia.pl', 'name' => 'Ola Zielińska', 'bio' => 'Prowadzi zajęcia z angielskiego przez zabawę, piosenki i gry — uczy najmłodszych od lat.'],
        ['email' => 'kasia@innova-pracownia.pl', 'name' => 'Kasia Wiśniewska', 'bio' => 'Instruktorka teatralna, pomaga dzieciom odkryć śmiałość na scenie.'],
        ['email' => 'marek@innova-pracownia.pl', 'name' => 'Marek Kowalski', 'bio' => 'Pasjonat robotyki i programowania — uczy budować i programować pierwsze roboty.'],
        ['email' => 'ania@innova-pracownia.pl', 'name' => 'Ania Nowak', 'bio' => 'Prowadzi zajęcia plastyczne i rękodzielnicze w luźnej, twórczej atmosferze.'],
        ['email' => 'beata@innova-pracownia.pl', 'name' => 'Beata Kowalczyk', 'bio' => 'Uczy matematyki przez zabawę — od pierwszych liczb po przygotowanie do egzaminu ósmoklasisty.'],
        ['email' => 'tomek@innova-pracownia.pl', 'name' => 'Tomek Nowicki', 'bio' => 'Prowadzi bezpieczne eksperymenty naukowe, które pokazują dzieciom, jak działa świat.'],
    ];
    $instructorPassword = 'Prowadzacy123!';
    $instructorIds = [];
    foreach ($instructorDefs as $def) {
        // $updateData ogranicza się do bio — jeśli konto już istnieje, seed
        // nie nadpisuje imienia/hasła/zdjęcia, które admin mógł zmienić w
        // /admin-prowadzacy-edytuj.php (tak samo jak w pierwotnym seed.ts).
        $id = seed_upsert_user(
            $pdo,
            $def['email'],
            ['name' => $def['name'], 'bio' => $def['bio'], 'role' => 'INSTRUCTOR', 'password_hash' => hash_password($instructorPassword)],
            ['bio' => $def['bio']]
        );
        $instructorIds[$def['email']] = $id;
    }
    $log[] = 'Prowadzący hasło startowe (nowe konta): ' . $instructorPassword . ' (istniejące konta zachowują swoje hasła/dane z panelu)';

    // --- Rodzaje zajęć + realne grupy wiekowe, zgodnie z prawdziwym grafikiem
    //     pracowni (odczytanym z odręcznej tabeli: Poniedziałek/Wtorek/Piątek —
    //     Środa i Czwartek bez zajęć). dayOffset liczony od SEMESTER_START
    //     (poniedziałek): 0=pon, 1=wt, 4=pt. ---
    $classTypeDefs = [
        [
            'key' => 'ENGLISH', 'name' => 'Angielski',
            'description' => 'Nauka angielskiego przez zabawę, piosenki, gry i krótkie dialogi — zajęcia prowadzone w małych grupach, dopasowane do wieku i poziomu dziecka.',
            'color' => '#8f8a56', 'age_min' => 3, 'age_max' => 12, 'instructor' => 'ola@innova-pracownia.pl',
            'groups' => [
                ['label' => '', 'age_label' => '3–4 lata', 'duration' => 35, 'price' => 149, 'day' => 1, 'h' => 13, 'm' => 0],
                ['label' => '', 'age_label' => '5–7 lat', 'duration' => 50, 'price' => 199, 'day' => 1, 'h' => 13, 'm' => 50],
                ['label' => '', 'age_label' => '8–12 lat', 'duration' => 60, 'price' => 219, 'day' => 1, 'h' => 19, 'm' => 0],
            ],
        ],
        [
            'key' => 'THEATER', 'name' => 'Zajęcia sceniczne',
            'description' => 'Improwizacja, dykcja, praca z ciałem i głosem oraz przygotowywanie krótkich etiud — zajęcia budujące pewność siebie, wyobraźnię i swobodę wyrażania emocji.',
            'color' => '#b98a8d', 'age_min' => 6, 'age_max' => 15, 'instructor' => 'kasia@innova-pracownia.pl',
            'groups' => [
                ['label' => 'Scena', 'age_label' => '6–9 lat', 'duration' => 60, 'price' => 199, 'day' => 1, 'h' => 15, 'm' => 0],
                ['label' => 'Słowo na scenie', 'age_label' => '9–15 lat', 'duration' => 85, 'price' => 249, 'day' => 1, 'h' => 16, 'm' => 10],
                // Druga grupa "Słowo na scenie MIX" (wtorek, po "Słowo na scenie")
                // celowo pominięta — godzina na grafiku nieczytelna ("16:80-17:85",
                // niemożliwa). Dodaj ją ręcznie w „+ Nowe zajęcia", gdy znasz godzinę.
            ],
        ],
        [
            'key' => 'ROBOTICS', 'name' => 'Robotyka',
            'description' => 'Budowanie i programowanie prostych robotów oraz automatów — dzieci uczą się podstaw elektroniki, logicznego myślenia i programowania blokowego w przyjaznej, praktycznej formie.',
            'color' => '#6b6642', 'age_min' => 5, 'age_max' => 10, 'instructor' => 'marek@innova-pracownia.pl',
            'groups' => [
                ['label' => 'Robotyka „1”', 'age_label' => '5–7 lat', 'duration' => 60, 'price' => 249, 'day' => 0, 'h' => 13, 'm' => 0],
                ['label' => 'Robotyka „2”', 'age_label' => '8–10 lat', 'duration' => 60, 'price' => 249, 'day' => 0, 'h' => 14, 'm' => 0],
                ['label' => 'Robotyka „3”', 'age_label' => '8–10 lat', 'duration' => 60, 'price' => 249, 'day' => 4, 'h' => 15, 'm' => 10],
            ],
        ],
        [
            'key' => 'CREATIVE', 'name' => 'Zajęcia kreatywne',
            'description' => 'Malarstwo, rękodzieło, prace plastyczne i eksperymenty z różnymi materiałami — rozwijamy wyobraźnię i zdolności manualne najmłodszych w luźnej, artystycznej atmosferze.',
            'color' => '#d9a3a6', 'age_min' => 5, 'age_max' => 15, 'instructor' => 'ania@innova-pracownia.pl',
            'groups' => [
                ['label' => 'Mikrokreatywny', 'age_label' => '8–11 lat', 'duration' => 50, 'price' => 229, 'day' => 0, 'h' => 15, 'm' => 10],
                ['label' => 'Mix kreatywny', 'age_label' => '5–7 lat', 'duration' => 60, 'price' => 229, 'day' => 0, 'h' => 16, 'm' => 10],
                ['label' => 'Szydełkowanie', 'age_label' => '9–15 lat', 'duration' => 75, 'price' => 229, 'fee' => 79, 'day' => 0, 'h' => 17, 'm' => 20],
            ],
        ],
        [
            'key' => 'MATH', 'name' => 'Matematyka',
            'description' => 'Matematyczne odkrycia przez zabawę, oswajanie z liczbami i logiczne myślenie dla najmłodszych, a dla starszych — pomoc szkolna, nadrabianie zaległości i przygotowanie do egzaminu ósmoklasisty.',
            'color' => '#c9a768', 'age_min' => 4, 'age_max' => 15, 'instructor' => 'beata@innova-pracownia.pl',
            'groups' => [
                ['label' => 'Matematyczne odkrycia', 'age_label' => '4–5 lat', 'duration' => 35, 'price' => 149, 'day' => 4, 'h' => 13, 'm' => 0],
                ['label' => 'Logika + pomoc szkolna', 'age_label' => 'klasy 1–3', 'duration' => 60, 'price' => 199, 'day' => 4, 'h' => 17, 'm' => 40],
                ['label' => '', 'age_label' => 'klasy 6–8', 'duration' => 50, 'price' => 199, 'day' => 4, 'h' => 18, 'm' => 45],
            ],
        ],
        [
            'key' => 'SCIENCE', 'name' => 'Eksperymentatorium',
            'description' => 'Bezpieczne eksperymenty chemiczne i fizyczne, które tłumaczą, jak działa świat — dzieci samodzielnie odkrywają zjawiska naukowe pod okiem prowadzącego, ucząc się przez działanie.',
            'color' => '#a8a473', 'age_min' => 6, 'age_max' => 15, 'instructor' => 'tomek@innova-pracownia.pl',
            'groups' => [
                ['label' => '', 'age_label' => '6–9 lat', 'duration' => 60, 'price' => 229, 'day' => 0, 'h' => 18, 'm' => 40],
                // Piątek 16:10-16:35 (25 min) — dokładnie tak jak na grafiku; jeśli
                // to literówka i miało być dłużej, popraw w /admin-cennik.php i
                // usuń/dodaj terminy w „Dostępność terminów".
                ['label' => '', 'age_label' => '10–15 lat', 'duration' => 25, 'price' => 249, 'day' => 4, 'h' => 16, 'm' => 10],
            ],
        ],
    ];

    $weeksToGenerate = 10;
    foreach ($classTypeDefs as $def) {
        [$classTypeId, $classTypeIsNew] = seed_upsert_class_type($pdo, $def['key'], [
            'name' => $def['name'], 'description' => $def['description'], 'color' => $def['color'],
            'age_min' => $def['age_min'], 'age_max' => $def['age_max'],
        ]);
        $instructorId = $instructorIds[$def['instructor']];
        $instructorName = $instructorDefs[array_search($def['instructor'], array_column($instructorDefs, 'email'), true)]['name'];

        // Usuwa STARE terminy tego rodzaju zajęć, które nie mają żadnego
        // zgłoszenia — pozwala bezpiecznie podmienić cały grafik (dni/godziny)
        // na nowy, bez ręcznego kasowania w panelu i BEZ ryzyka utraty
        // prawdziwych zgłoszeń rodziców (te sesje zawsze zostają nietknięte).
        $pdo->prepare('DELETE FROM class_sessions WHERE class_type_id = ? AND id NOT IN (SELECT DISTINCT session_id FROM enrollments)')
            ->execute([$classTypeId]);

        // Cennik zakładamy TYLKO przy pierwszym utworzeniu tego rodzaju zajęć.
        // Gdy rodzaj zajęć już istnieje, seed w ogóle nie rusza pricing_tiers —
        // od tego momentu cennik zarządzany jest ręcznie w /admin-cennik.php i
        // ponowne uruchomienie seeda (np. przy aktualizacji grafiku) nie może
        // cofnąć wprowadzonych tam zmian cen.
        if ($classTypeIsNew) {
            foreach ($def['groups'] as $i => $g) {
                $pdo->prepare('INSERT INTO pricing_tiers (class_type_id, label, age_label, duration_min, price_monthly, one_time_fee, sort_order) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$classTypeId, $g['label'], $g['age_label'], $g['duration'], $g['price'], $g['fee'] ?? null, $i]);
            }
        }

        $total = 0;
        foreach ($def['groups'] as $g) {
            $title = $g['label'] ? "{$g['label']} — {$g['age_label']}" : $g['age_label'];
            $startTime = sprintf('%02d:%02d', $g['h'], $g['m']);
            $anchor = new DateTime(SEMESTER_START);
            $anchor->modify('+' . $g['day'] . ' days');
            $endsAnchor = (clone $anchor)->setTime($g['h'], $g['m'])->modify('+' . $g['duration'] . ' minutes');
            $endTime = $endsAnchor->format('H:i');

            $groupId = seed_upsert_group($pdo, $classTypeId, $title, [
                'instructor_id' => $instructorId, 'instructor_name' => $instructorName,
                'day_of_week' => (int) $anchor->format('N'), 'start_time' => $startTime, 'end_time' => $endTime,
                'capacity' => MAX_GROUP_SIZE, 'meeting_url' => 'https://meet.innova-pracownia.pl/demo-room',
            ]);

            for ($w = 0; $w < $weeksToGenerate; $w++) {
                $starts = clone $anchor;
                $starts->modify('+' . $w . ' weeks');
                $starts->setTime($g['h'], $g['m']);
                $ends = clone $starts;
                $ends->modify('+' . $g['duration'] . ' minutes');

                $startsStr = $starts->format('Y-m-d H:i:s');
                $check = $pdo->prepare('SELECT id FROM class_sessions WHERE class_type_id = ? AND title = ? AND starts_at = ?');
                $check->execute([$classTypeId, $title, $startsStr]);
                if ($check->fetch()) {
                    // Sesja już istnieje (np. z czasów sprzed wprowadzenia grup) —
                    // dopinamy jej group_id, gdyby jeszcze go nie miała.
                    $pdo->prepare('UPDATE class_sessions SET group_id = ? WHERE class_type_id = ? AND title = ? AND starts_at = ? AND group_id IS NULL')
                        ->execute([$groupId, $classTypeId, $title, $startsStr]);
                    continue;
                }
                $pdo->prepare('INSERT INTO class_sessions (class_type_id, group_id, title, starts_at, ends_at, capacity, meeting_url, instructor_id, instructor_name) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$classTypeId, $groupId, $title, $startsStr, $ends->format('Y-m-d H:i:s'), MAX_GROUP_SIZE, 'https://meet.innova-pracownia.pl/demo-room', $instructorId, $instructorName]);
                $total++;
            }
        }
        $log[] = "{$def['name']}: " . count($def['groups']) . " grup, $total terminów łącznie";
    }

    // --- Dzień otwarty (jednorazowe wydarzenie przed startem semestru) ---
    [$openDayTypeId] = seed_upsert_class_type($pdo, 'OPEN_DAY', [
        'name' => 'Dzień otwarty',
        'description' => 'Bezpłatne zajęcia pokazowe — poznaj pracownię, prowadzących i ofertę zajęć przed startem zapisów na semestr.',
        'color' => '#cf9a7c', 'age_min' => 0, 'age_max' => 99,
    ]);
    $pdo->prepare('DELETE FROM pricing_tiers WHERE class_type_id = ?')->execute([$openDayTypeId]);
    $openDayStart = OPEN_DAY_DATE . ' 10:00:00';
    $openDayEnd = OPEN_DAY_DATE . ' 13:00:00';
    $check = $pdo->prepare('SELECT id FROM class_sessions WHERE class_type_id = ? AND starts_at = ?');
    $check->execute([$openDayTypeId, $openDayStart]);
    if (!$check->fetch()) {
        $pdo->prepare('INSERT INTO class_sessions (class_type_id, title, starts_at, ends_at, capacity, instructor_id, instructor_name) VALUES (?,?,?,?,?,?,?)')
            ->execute([$openDayTypeId, 'Dzień otwarty — bezpłatne zajęcia pokazowe', $openDayStart, $openDayEnd, 30, $adminId, 'Zespół INNOVA']);
    }
    $log[] = 'Dzień otwarty: ' . format_pl_date(OPEN_DAY_DATE) . ', 10:00–13:00 (limit 30 osób)';

    // --- Przykładowy rodzic + dziecko + jeden potwierdzony zapis ---
    // Bez $updateData: jeśli ktoś już zmienił hasło/dane tego konta (albo je
    // usunął — patrz DEPLOY_HOMEPL.md), seed tego nie cofa/nie odtwarza.
    $demoParentId = seed_upsert_user($pdo, 'rodzic@example.com', [
        'name' => 'Testowy Rodzic', 'phone' => '500600700', 'role' => 'PARENT',
        'password_hash' => hash_password('Haslo123!'),
    ]);
    $existingChild = $pdo->prepare('SELECT id FROM children WHERE parent_id = ? AND first_name = ? AND last_name = ?');
    $existingChild->execute([$demoParentId, 'Zosia', 'Testowa']);
    $childRow = $existingChild->fetch();
    if ($childRow) {
        $demoChildId = (int) $childRow['id'];
    } else {
        $pdo->prepare('INSERT INTO children (parent_id, first_name, last_name, birth_date) VALUES (?,?,?,?)')
            ->execute([$demoParentId, 'Zosia', 'Testowa', '2018-05-14']);
        $demoChildId = db_last_id($pdo);
    }

    $firstRobotics = $pdo->query("SELECT cs.id, cs.capacity FROM class_sessions cs
        JOIN class_types ct ON ct.id = cs.class_type_id
        WHERE ct.key_name = 'ROBOTICS' ORDER BY cs.starts_at ASC LIMIT 1")->fetch();
    if ($firstRobotics) {
        $existingEnrollment = $pdo->prepare('SELECT id FROM enrollments WHERE session_id = ? AND child_id = ?');
        $existingEnrollment->execute([$firstRobotics['id'], $demoChildId]);
        if (!$existingEnrollment->fetch()) {
            $pdo->prepare('INSERT INTO enrollments (session_id, child_id, parent_id, status, confirmed_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)')
                ->execute([$firstRobotics['id'], $demoChildId, $demoParentId, 'CONFIRMED']);
        }
    }
    $log[] = 'Demo rodzic: rodzic@example.com / Haslo123!';

    // --- Dowiąż group_id/class_type_id do STARYCH zgłoszeń (sprzed grup) ---
    // Każde zgłoszenie sprzed tej zmiany (i powyższy demo-zapis) ma tylko
    // session_id — zajętość grupy liczy się teraz po group_id (patrz
    // get_sessions_with_availability), więc bez tego backfillu prawdziwe,
    // już potwierdzone zapisy z produkcji wyglądałyby, jakby ich zajęcia
    // były puste. Nie rusza zgłoszeń, które już mają group_id (np.
    // przydzielonych ręcznie w panelu grup).
    $pdo->exec('UPDATE enrollments SET group_id = (SELECT cs.group_id FROM class_sessions cs WHERE cs.id = enrollments.session_id)
                WHERE group_id IS NULL AND session_id IS NOT NULL');
    $pdo->exec('UPDATE enrollments SET class_type_id = (SELECT cs.class_type_id FROM class_sessions cs WHERE cs.id = enrollments.session_id)
                WHERE class_type_id IS NULL AND session_id IS NOT NULL');

    $log[] = 'Seed zakończony.';

    return $log;
}
