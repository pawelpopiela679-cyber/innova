<?php
/**
 * Dane startowe: 3 plany subskrypcji, konto super-admina (Ty — właściciel
 * platformy), oraz jedna demo-organizacja z adminem, prowadzącym, rodzajem
 * zajęć, terminami i przykładowymi zapisami (w tym jeden z nieobecnością
 * do przetestowania „odrabiania zajęć" i jeden nieopłacony). Bezpieczne do
 * wielokrotnego uruchomienia.
 */

function seed_upsert_user_zp(PDO $pdo, string $email, array $createData, array $updateData = []): int
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

function seed_upsert_plan(PDO $pdo, string $key, array $data): int
{
    $existing = $pdo->prepare('SELECT id FROM subscription_plans WHERE key_name = ?');
    $existing->execute([$key]);
    $row = $existing->fetch();
    if ($row) {
        $pdo->prepare('UPDATE subscription_plans SET name=?, price_monthly=?, max_instructors=?, max_students=?, sort_order=? WHERE id=?')
            ->execute([$data['name'], $data['price'], $data['max_instructors'], $data['max_students'], $data['sort'], $row['id']]);
        return (int) $row['id'];
    }
    $pdo->prepare('INSERT INTO subscription_plans (key_name, name, price_monthly, max_instructors, max_students, sort_order) VALUES (?,?,?,?,?,?)')
        ->execute([$key, $data['name'], $data['price'], $data['max_instructors'], $data['max_students'], $data['sort']]);
    return db_last_id($pdo);
}

/** @return string[] log linijek do pokazania na stronie install.php */
function run_seed(): array
{
    $pdo = db();
    $log = [];

    // --- Plany subskrypcji (ceny w groszach) ---
    $starterId = seed_upsert_plan($pdo, 'START', ['name' => 'Start', 'price' => 9900, 'max_instructors' => 1, 'max_students' => 30, 'sort' => 0]);
    $proId = seed_upsert_plan($pdo, 'PRO', ['name' => 'Pro', 'price' => 19900, 'max_instructors' => 5, 'max_students' => 150, 'sort' => 1]);
    seed_upsert_plan($pdo, 'BIZNES', ['name' => 'Biznes', 'price' => 34900, 'max_instructors' => 999, 'max_students' => 999999, 'sort' => 2]);
    $log[] = 'Plany subskrypcji: Start (99 zł), Pro (199 zł), Biznes (349 zł) / mies.';

    // --- Super-admin (właściciel platformy ZapisyPro) ---
    seed_upsert_user_zp($pdo, SEED_SUPERADMIN_EMAIL, [
        'org_id' => null, 'name' => 'Super Admin', 'role' => 'SUPER_ADMIN',
        'password_hash' => hash_password(SEED_SUPERADMIN_PASSWORD),
    ]);
    $log[] = 'Super-admin: ' . SEED_SUPERADMIN_EMAIL . ' / hasło startowe: ' . SEED_SUPERADMIN_PASSWORD;

    // --- Demo-organizacja (przykładowy klient SaaS-a, plan Pro w okresie próbnym) ---
    $slug = 'demo-szkola';
    $orgStmt = $pdo->prepare('SELECT id FROM organizations WHERE slug = ?');
    $orgStmt->execute([$slug]);
    $orgRow = $orgStmt->fetch();
    if ($orgRow) {
        $orgId = (int) $orgRow['id'];
    } else {
        $trialEnds = (new DateTime('+' . TRIAL_DAYS . ' days'))->format('Y-m-d');
        $pdo->prepare("INSERT INTO organizations (name, slug, notify_email, status, plan_id, trial_ends_at) VALUES (?,?,?,?,?,?)")
            ->execute(['Demo Szkółka Rozwoju', $slug, SEED_DEMO_ORG_ADMIN_EMAIL, 'TRIAL', $proId, $trialEnds]);
        $orgId = db_last_id($pdo);
    }
    $log[] = 'Demo-organizacja: „Demo Szkółka Rozwoju” (link do rejestracji rodziców: rejestracja.php?org=' . $slug . ')';

    $orgAdminId = seed_upsert_user_zp($pdo, SEED_DEMO_ORG_ADMIN_EMAIL, [
        'org_id' => $orgId, 'name' => 'Właściciel Demo Szkółki', 'role' => 'ORG_ADMIN',
        'password_hash' => hash_password(SEED_DEMO_ORG_ADMIN_PASSWORD),
    ]);
    $log[] = 'Admin demo-organizacji: ' . SEED_DEMO_ORG_ADMIN_EMAIL . ' / hasło startowe: ' . SEED_DEMO_ORG_ADMIN_PASSWORD;

    $instructorId = seed_upsert_user_zp($pdo, 'prowadzacy@demo-szkola.pl', [
        'org_id' => $orgId, 'name' => 'Anna Instruktor', 'role' => 'INSTRUCTOR',
        'bio' => 'Prowadzi zajęcia plastyczne i taneczne.', 'wage_hourly_cents' => 6000,
        'password_hash' => hash_password('Prowadzacy123!'),
    ]);
    $log[] = 'Prowadzący demo: prowadzacy@demo-szkola.pl / Prowadzacy123!';

    // --- Rodzaj zajęć + terminy (4 tygodnie do przodu, wtorki i czwartki) ---
    $ctStmt = $pdo->prepare('SELECT id FROM class_types WHERE org_id = ? AND key_name = ?');
    $ctStmt->execute([$orgId, 'PLASTYKA']);
    $ctRow = $ctStmt->fetch();
    if ($ctRow) {
        $classTypeId = (int) $ctRow['id'];
    } else {
        $pdo->prepare('INSERT INTO class_types (org_id, key_name, name, description, color, age_min, age_max) VALUES (?,?,?,?,?,?,?)')
            ->execute([$orgId, 'PLASTYKA', 'Zajęcia plastyczne', 'Malarstwo i rękodzieło dla dzieci.', '#4338ca', 5, 10]);
        $classTypeId = db_last_id($pdo);
        $pdo->prepare('INSERT INTO pricing_tiers (class_type_id, label, age_label, duration_min, price_monthly, sort_order) VALUES (?,?,?,?,?,?)')
            ->execute([$classTypeId, '', '5–10 lat', 60, 15900, 0]);
    }

    $anchor = new DateTime('next tuesday');
    $sessionIds = [];
    for ($w = 0; $w < 4; $w++) {
        foreach ([0, 2] as $dayOffset) { // wtorek (+0) i czwartek (+2)
            $starts = (clone $anchor)->modify('+' . ($w * 7 + $dayOffset) . ' days')->setTime(16, 0);
            $ends = (clone $starts)->modify('+60 minutes');
            $startsStr = $starts->format('Y-m-d H:i:s');
            $check = $pdo->prepare('SELECT id FROM class_sessions WHERE class_type_id = ? AND starts_at = ?');
            $check->execute([$classTypeId, $startsStr]);
            $existing = $check->fetch();
            if ($existing) {
                $sessionIds[] = (int) $existing['id'];
                continue;
            }
            $pdo->prepare('INSERT INTO class_sessions (org_id, class_type_id, title, starts_at, ends_at, capacity, instructor_id, instructor_name) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$orgId, $classTypeId, 'Zajęcia plastyczne', $startsStr, $ends->format('Y-m-d H:i:s'), 10, $instructorId, 'Anna Instruktor']);
            $sessionIds[] = db_last_id($pdo);
        }
    }
    $log[] = 'Zajęcia plastyczne: ' . count($sessionIds) . ' terminów (wtorki/czwartki, 16:00) na najbliższe 4 tygodnie';

    // --- Przykładowy rodzic + dziecko + zapisy demonstrujące nowe funkcje ---
    $parentId = seed_upsert_user_zp($pdo, 'rodzic@demo-szkola.pl', [
        'org_id' => $orgId, 'name' => 'Testowy Rodzic', 'phone' => '500600700', 'role' => 'PARENT',
        'password_hash' => hash_password('Haslo123!'),
    ]);
    $childCheck = $pdo->prepare('SELECT id FROM children WHERE parent_id = ? AND first_name = ?');
    $childCheck->execute([$parentId, 'Zosia']);
    $childRow = $childCheck->fetch();
    if ($childRow) {
        $childId = (int) $childRow['id'];
    } else {
        $pdo->prepare('INSERT INTO children (org_id, parent_id, first_name, last_name, birth_date) VALUES (?,?,?,?,?)')
            ->execute([$orgId, $parentId, 'Zosia', 'Testowa', '2017-03-10']);
        $childId = db_last_id($pdo);
    }

    if (count($sessionIds) >= 2) {
        // Zapis 1: potwierdzony, ale NIEOPŁACONY — demo statusu płatności.
        $e1 = $pdo->prepare('SELECT id FROM enrollments WHERE session_id = ? AND child_id = ?');
        $e1->execute([$sessionIds[0], $childId]);
        if (!$e1->fetch()) {
            $pdo->prepare("INSERT INTO enrollments (org_id, session_id, child_id, parent_id, status, payment_status, amount_due_cents, confirmed_at) VALUES (?,?,?,?,'CONFIRMED','UNPAID',15900,CURRENT_TIMESTAMP)")
                ->execute([$orgId, $sessionIds[0], $childId, $parentId]);
        }
        // Zapis 2: potwierdzony, opłacony, ale ze zgłoszoną nieobecnością — demo "odrabiania zajęć".
        $e2 = $pdo->prepare('SELECT id FROM enrollments WHERE session_id = ? AND child_id = ?');
        $e2->execute([$sessionIds[1], $childId]);
        if (!$e2->fetch()) {
            $pdo->prepare("INSERT INTO enrollments (org_id, session_id, child_id, parent_id, status, payment_status, attendance_status, absence_reported_at, confirmed_at) VALUES (?,?,?,?,'CONFIRMED','PAID','ABSENT',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
                ->execute([$orgId, $sessionIds[1], $childId, $parentId]);
        }
    }
    $log[] = 'Demo rodzic: rodzic@demo-szkola.pl / Haslo123! (jeden zapis nieopłacony, jeden z nieobecnością do odrobienia)';
    $log[] = 'Seed zakończony.';

    return $log;
}
