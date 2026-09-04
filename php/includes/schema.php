<?php
/**
 * Tworzy wszystkie tabele (CREATE TABLE IF NOT EXISTS — bezpieczne do
 * wielokrotnego uruchomienia), w dialekcie MySQL albo SQLite zależnie od
 * DB_DRIVER. Odpowiednik prisma/schema.prisma z wersji Next.js.
 */
function create_index_if_missing(PDO $pdo, string $indexName, string $table, string $column): void
{
    if (db_is_mysql()) {
        try {
            $pdo->exec("CREATE INDEX $indexName ON $table($column)");
        } catch (PDOException $e) {
            // Kod 1061 = "Duplicate key name" — indeks już istnieje, nic nie robimy.
            if (!str_contains($e->getMessage(), '1061')) {
                throw $e;
            }
        }
    } else {
        $pdo->exec("CREATE INDEX IF NOT EXISTS $indexName ON $table($column)");
    }
}

/**
 * Dokłada kolumnę do już istniejącej tabeli (produkcyjna baza na home.pl —
 * CREATE TABLE IF NOT EXISTS jej nie dotknie, bo tabela już istnieje).
 * Bezpieczne do wielokrotnego uruchomienia: łapie błąd "kolumna już
 * istnieje" zamiast sprawdzać z wyprzedzeniem (INFORMATION_SCHEMA różni się
 * między MySQL a SQLite, try/catch jest prostszy i pewniejszy).
 * Dla świeżej bazy SQLite nic nie robi — tam docelowy kształt kolumny jest
 * już w CREATE TABLE niżej.
 */
function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!db_is_mysql()) {
        return;
    }
    try {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    } catch (PDOException $e) {
        // 1060 = "Duplicate column name" — kolumna już istnieje, nic nie robimy.
        if (!str_contains($e->getMessage(), '1060')) {
            throw $e;
        }
    }
}

/** Zmienia typ/nullowalność istniejącej kolumny (np. session_id NOT NULL -> NULL). */
function modify_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!db_is_mysql()) {
        return;
    }
    // MODIFY COLUMN nie rzuca błędu, jeśli kolumna już ma taki kształt —
    // bezpieczne do wielokrotnego uruchomienia bez try/catch.
    $pdo->exec("ALTER TABLE $table MODIFY COLUMN $column $definition");
}

function ensure_schema(): void
{
    $pdo = db();
    $mysql = db_is_mysql();
    $pk = db_pk();
    $engine = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $pk,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(190) NOT NULL,
        phone VARCHAR(40) NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'PARENT',
        avatar_url VARCHAR(255) NULL,
        bio TEXT NULL,
        can_manage_groups TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )$engine");
    // Na istniejącej (produkcyjnej) bazie tabela users już jest — dokładamy
    // kolumnę uprawnień, jeśli jeszcze jej nie ma.
    add_column_if_missing($pdo, 'users', 'can_manage_groups', 'TINYINT(1) NOT NULL DEFAULT 0');

    $pdo->exec("CREATE TABLE IF NOT EXISTS class_types (
        id $pk,
        key_name VARCHAR(60) NOT NULL UNIQUE,
        name VARCHAR(190) NOT NULL,
        description TEXT NOT NULL,
        color VARCHAR(20) NOT NULL DEFAULT '#6366f1',
        age_min INT NOT NULL DEFAULT 5,
        age_max INT NOT NULL DEFAULT 12,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
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
        parent_id INT NOT NULL,
        first_name VARCHAR(120) NOT NULL,
        last_name VARCHAR(120) NOT NULL,
        birth_date DATE NOT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
    )$engine");

    /**
     * "Grupa" — trwały byt, osobny od pojedynczego terminu w kalendarzu:
     * dzieci zapisują się DO GRUPY (raz), grupa ma stały dzień/godzinę i
     * listę dzieci, a class_sessions niżej to tylko jej kolejne cotygodniowe
     * "wystąpienia" w kalendarzu (do widoku grafiku/listy zajęć). Dzięki
     * temu można np. wysłać jeden e-mail do wszystkich rodziców z grupy,
     * zamiast do każdego zgłoszenia/terminu osobno.
     * Nazwa "class_groups" (nie "groups") — GROUPS jest słowem zastrzeżonym
     * w MySQL 8+ (funkcje okienkowe).
     */
    $pdo->exec("CREATE TABLE IF NOT EXISTS class_groups (
        id $pk,
        class_type_id INT NOT NULL,
        name VARCHAR(190) NOT NULL,
        instructor_id INT NULL,
        instructor_name VARCHAR(190) NOT NULL,
        day_of_week INT NOT NULL,
        start_time VARCHAR(5) NOT NULL,
        end_time VARCHAR(5) NOT NULL,
        capacity INT NOT NULL DEFAULT 10,
        meeting_url VARCHAR(255) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (class_type_id) REFERENCES class_types(id) ON DELETE CASCADE,
        FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL
    )$engine");
    create_index_if_missing($pdo, 'idx_groups_class_type', 'class_groups', 'class_type_id');

    $pdo->exec("CREATE TABLE IF NOT EXISTS class_sessions (
        id $pk,
        class_type_id INT NOT NULL,
        group_id INT NULL,
        title VARCHAR(190) NOT NULL,
        description TEXT NULL,
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NOT NULL,
        capacity INT NOT NULL DEFAULT 10,
        meeting_url VARCHAR(255) NULL,
        instructor_id INT NULL,
        instructor_name VARCHAR(190) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'SCHEDULED',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (class_type_id) REFERENCES class_types(id) ON DELETE CASCADE,
        FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL
    )$engine");
    // Zwykły MySQL (w odróżnieniu od MariaDB i SQLite) nie zna składni
    // "CREATE INDEX IF NOT EXISTS" — łapiemy więc błąd "już istnieje" przy
    // ponownym uruchomieniu install.php zamiast go sprawdzać z wyprzedzeniem.
    create_index_if_missing($pdo, 'idx_sessions_starts_at', 'class_sessions', 'starts_at');
    create_index_if_missing($pdo, 'idx_sessions_class_type', 'class_sessions', 'class_type_id');
    add_column_if_missing($pdo, 'class_sessions', 'group_id', 'INT NULL');
    create_index_if_missing($pdo, 'idx_sessions_group', 'class_sessions', 'group_id');

    $pdo->exec("CREATE TABLE IF NOT EXISTS enrollments (
        id $pk,
        session_id INT NULL,
        class_type_id INT NULL,
        group_id INT NULL,
        child_id INT NOT NULL,
        parent_id INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        confirmed_at DATETIME NULL,
        canceled_at DATETIME NULL,
        UNIQUE (session_id, child_id),
        FOREIGN KEY (session_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (child_id) REFERENCES children(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE
    )$engine");
    create_index_if_missing($pdo, 'idx_enrollments_parent', 'enrollments', 'parent_id');
    create_index_if_missing($pdo, 'idx_enrollments_group', 'enrollments', 'group_id');
    // session_id był kiedyś wymagany (rodzic wybierał konkretny termin z
    // kalendarza) — teraz zgłoszenie wiąże się z rodzajem zajęć (class_type_id),
    // a dopiero po przydzieleniu do grupy dostaje group_id. session_id
    // zostaje tylko dla starych zgłoszeń sprzed tej zmiany.
    modify_column($pdo, 'enrollments', 'session_id', 'INT NULL');
    add_column_if_missing($pdo, 'enrollments', 'class_type_id', 'INT NULL');
    add_column_if_missing($pdo, 'enrollments', 'group_id', 'INT NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
        id VARCHAR(20) PRIMARY KEY,
        background VARCHAR(20) NOT NULL,
        foreground VARCHAR(20) NOT NULL,
        surface VARCHAR(20) NOT NULL,
        border VARCHAR(20) NOT NULL,
        primary_color VARCHAR(20) NOT NULL,
        primary_light VARCHAR(20) NOT NULL,
        accent VARCHAR(20) NOT NULL,
        gold VARCHAR(20) NOT NULL,
        muted VARCHAR(20) NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
        id $pk,
        slug VARCHAR(120) NOT NULL UNIQUE,
        title VARCHAR(190) NOT NULL,
        content TEXT NOT NULL,
        show_in_nav TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )$engine");
}
