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
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )$engine");

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

    $pdo->exec("CREATE TABLE IF NOT EXISTS class_sessions (
        id $pk,
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
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (class_type_id) REFERENCES class_types(id) ON DELETE CASCADE,
        FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL
    )$engine");
    // Zwykły MySQL (w odróżnieniu od MariaDB i SQLite) nie zna składni
    // "CREATE INDEX IF NOT EXISTS" — łapiemy więc błąd "już istnieje" przy
    // ponownym uruchomieniu install.php zamiast go sprawdzać z wyprzedzeniem.
    create_index_if_missing($pdo, 'idx_sessions_starts_at', 'class_sessions', 'starts_at');
    create_index_if_missing($pdo, 'idx_sessions_class_type', 'class_sessions', 'class_type_id');

    $pdo->exec("CREATE TABLE IF NOT EXISTS enrollments (
        id $pk,
        session_id INT NOT NULL,
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
