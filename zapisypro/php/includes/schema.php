<?php
/**
 * Tworzy wszystkie tabele (CREATE TABLE IF NOT EXISTS — bezpieczne do
 * wielokrotnego uruchomienia), w dialekcie MySQL albo SQLite. Wersja
 * wielo-najemcza: prawie każda tabela ma org_id, żeby dane różnych
 * organizacji (klientów SaaS-a) nigdy się nie mieszały.
 */
function create_index_if_missing(PDO $pdo, string $indexName, string $table, string $column): void
{
    if (db_is_mysql()) {
        try {
            $pdo->exec("CREATE INDEX $indexName ON $table($column)");
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), '1061')) { // 1061 = już istnieje
                throw $e;
            }
        }
    } else {
        $pdo->exec("CREATE INDEX IF NOT EXISTS $indexName ON $table($column)");
    }
}

/**
 * Dodaje kolumnę do istniejącej tabeli, jeśli jej jeszcze nie ma — bezpieczne
 * do wielokrotnego uruchomienia (w odróżnieniu od CREATE TABLE, ALTER TABLE
 * ADD COLUMN nie ma jednej wspólnej składni "IF NOT EXISTS" w MySQL/SQLite,
 * więc sprawdzamy ręcznie). Potrzebne, żeby install.php mógł donstalować
 * nowe pola (np. enrollments.paid_at) na instalacji, która już wcześniej
 * istniała, bez utraty danych.
 */
function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (db_is_mysql()) {
        $stmt = $pdo->prepare("SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $stmt->execute([$table, $column]);
        if ((int) $stmt->fetch()['c'] > 0) {
            return;
        }
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    } else {
        $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll();
        foreach ($cols as $c) {
            if ($c['name'] === $column) {
                return;
            }
        }
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

function ensure_schema(): void
{
    $pdo = db();
    $mysql = db_is_mysql();
    $pk = db_pk();
    $engine = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

    $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_plans (
        id $pk,
        key_name VARCHAR(40) NOT NULL UNIQUE,
        name VARCHAR(120) NOT NULL,
        price_monthly INT NOT NULL DEFAULT 0,
        max_instructors INT NOT NULL DEFAULT 1,
        max_students INT NOT NULL DEFAULT 20,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS organizations (
        id $pk,
        name VARCHAR(190) NOT NULL,
        slug VARCHAR(120) NOT NULL UNIQUE,
        notify_email VARCHAR(190) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'TRIAL',
        plan_id INT NULL,
        trial_ends_at DATE NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $pk,
        org_id INT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(190) NOT NULL,
        phone VARCHAR(40) NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'PARENT',
        avatar_url VARCHAR(255) NULL,
        bio TEXT NULL,
        wage_hourly_cents INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE
    )$engine");
    create_index_if_missing($pdo, 'idx_users_org', 'users', 'org_id');

    $pdo->exec("CREATE TABLE IF NOT EXISTS class_types (
        id $pk,
        org_id INT NOT NULL,
        key_name VARCHAR(60) NOT NULL,
        name VARCHAR(190) NOT NULL,
        description TEXT NOT NULL,
        color VARCHAR(20) NOT NULL DEFAULT '#6366f1',
        age_min INT NOT NULL DEFAULT 5,
        age_max INT NOT NULL DEFAULT 12,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (org_id, key_name),
        FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS pricing_tiers (
        id $pk,
        class_type_id INT NOT NULL,
        label VARCHAR(190) NOT NULL DEFAULT '',
        age_label VARCHAR(60) NOT NULL,
        duration_min INT NOT NULL,
        price_monthly INT NOT NULL,
        one_time_fee INT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        FOREIGN KEY (class_type_id) REFERENCES class_types(id) ON DELETE CASCADE
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS children (
        id $pk,
        org_id INT NOT NULL,
        parent_id INT NOT NULL,
        first_name VARCHAR(120) NOT NULL,
        last_name VARCHAR(120) NOT NULL,
        birth_date DATE NOT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS class_sessions (
        id $pk,
        org_id INT NOT NULL,
        class_type_id INT NOT NULL,
        title VARCHAR(190) NOT NULL,
        description TEXT NULL,
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NOT NULL,
        capacity INT NOT NULL DEFAULT 10,
        meeting_url VARCHAR(255) NULL,
        instructor_id INT NULL,
        instructor_name VARCHAR(190) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'SCHEDULED',
        is_makeup TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
        FOREIGN KEY (class_type_id) REFERENCES class_types(id) ON DELETE CASCADE,
        FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL
    )$engine");
    create_index_if_missing($pdo, 'idx_sessions_starts_at', 'class_sessions', 'starts_at');
    create_index_if_missing($pdo, 'idx_sessions_org', 'class_sessions', 'org_id');

    $pdo->exec("CREATE TABLE IF NOT EXISTS enrollments (
        id $pk,
        org_id INT NOT NULL,
        session_id INT NOT NULL,
        child_id INT NOT NULL,
        parent_id INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
        attendance_status VARCHAR(20) NULL,
        absence_reported_at DATETIME NULL,
        rescheduled_to_enrollment_id INT NULL,
        payment_status VARCHAR(20) NOT NULL DEFAULT 'UNPAID',
        amount_due_cents INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        confirmed_at DATETIME NULL,
        canceled_at DATETIME NULL,
        UNIQUE (session_id, child_id),
        FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
        FOREIGN KEY (session_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
    )$engine");
    create_index_if_missing($pdo, 'idx_enrollments_parent', 'enrollments', 'parent_id');
    create_index_if_missing($pdo, 'idx_enrollments_org', 'enrollments', 'org_id');

    // Kiedy zapis realnie został opłacony — do raportów przychodu wg okresu
    // (payment_status samo w sobie mówi tylko "opłacone/nie", bez daty).
    ensure_column($pdo, 'enrollments', 'paid_at', 'DATETIME NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS contracts (
        id $pk,
        org_id INT NOT NULL,
        title VARCHAR(190) NOT NULL,
        content TEXT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS contract_acceptances (
        id $pk,
        org_id INT NOT NULL,
        contract_id INT NOT NULL,
        parent_id INT NOT NULL,
        signer_name VARCHAR(190) NOT NULL,
        accepted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (contract_id, parent_id),
        FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE,
        FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS org_settings (
        org_id INT PRIMARY KEY,
        background VARCHAR(20) NOT NULL,
        foreground VARCHAR(20) NOT NULL,
        surface VARCHAR(20) NOT NULL,
        border VARCHAR(20) NOT NULL,
        primary_color VARCHAR(20) NOT NULL,
        primary_light VARCHAR(20) NOT NULL,
        accent VARCHAR(20) NOT NULL,
        muted VARCHAR(20) NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (org_id) REFERENCES organizations(id) ON DELETE CASCADE
    )$engine");

    // Licznik nieudanych logowań (ochrona przed brute-force) — patrz includes/rate_limit.php.
    // Bez org_id celowo: identyfikator już zawiera e-mail albo IP, więc jeden
    // rekord wystarcza niezależnie od tego, do której organizacji ktoś próbuje
    // się dostać (a super-admin i tak nie ma org_id).
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        identifier VARCHAR(190) PRIMARY KEY,
        attempts INT NOT NULL DEFAULT 0,
        last_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        locked_until DATETIME NULL
    )$engine");
}
