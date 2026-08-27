<?php
/** Logika kalendarza i dostępności — odpowiednik lib/calendar-grid.ts + lib/availability.ts. */

function parse_date_param(?string $s): DateTime
{
    if ($s) {
        try {
            return new DateTime($s . ' 00:00:00');
        } catch (Throwable $e) {
            // spadnij do dzisiaj
        }
    }
    return new DateTime('today');
}

function date_param(DateTime $d): string
{
    return $d->format('Y-m-d');
}

/** Poniedziałek tygodnia zawierającego $d. */
function week_start(DateTime $d): DateTime
{
    $out = clone $d;
    $iso = (int) $out->format('N'); // 1 = poniedziałek ... 7 = niedziela
    $out->modify('-' . ($iso - 1) . ' days');
    return $out;
}

/** 7 kolejnych dni tygodnia (pon–nd) zawierającego $anchor. */
function build_week_days(DateTime $anchor): array
{
    $start = week_start($anchor);
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $d = clone $start;
        $d->modify("+$i days");
        $days[] = $d;
    }
    return $days;
}

/** Zakres [from,to) dla danego widoku ("day"|"week"|"month"). */
function range_for_view(string $view, DateTime $anchor): array
{
    if ($view === 'day') {
        $from = (clone $anchor)->setTime(0, 0);
        $to = (clone $from)->modify('+1 day');
        return [$from, $to];
    }
    if ($view === 'week') {
        $from = week_start($anchor)->setTime(0, 0);
        $to = (clone $from)->modify('+7 days');
        return [$from, $to];
    }
    // month: od poniedziałku pierwszego tygodnia do niedzieli ostatniego (siatka 6 tyg.)
    $firstOfMonth = new DateTime($anchor->format('Y-m-01'));
    $from = week_start($firstOfMonth);
    $to = (clone $from)->modify('+42 days');
    return [$from, $to];
}

/** Wszystkie dni siatki miesiąca (6 tygodni = 42 dni), z flagą czy należą do bieżącego miesiąca. */
function month_grid_days(DateTime $anchor): array
{
    [$from] = range_for_view('month', $anchor);
    $currentMonth = (int) $anchor->format('n');
    $days = [];
    for ($i = 0; $i < 42; $i++) {
        $d = (clone $from)->modify("+$i days");
        $days[] = ['date' => $d, 'inMonth' => (int) $d->format('n') === $currentMonth];
    }
    return $days;
}

/**
 * Terminy w zakresie [$from,$to) z dołączonym rodzajem zajęć i policzoną
 * dostępnością (ile potwierdzonych, ile wolnych, czy pełne).
 */
function get_sessions_with_availability(DateTime $from, DateTime $to, ?int $classTypeId = null): array
{
    $sql = "SELECT cs.*, ct.name AS ct_name, ct.color AS ct_color, ct.key_name AS ct_key,
                   (SELECT COUNT(*) FROM enrollments e WHERE e.session_id = cs.id AND e.status = 'CONFIRMED') AS confirmed_count
            FROM class_sessions cs
            JOIN class_types ct ON ct.id = cs.class_type_id
            WHERE cs.starts_at >= ? AND cs.starts_at < ?";
    $params = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];
    if ($classTypeId) {
        $sql .= ' AND cs.class_type_id = ?';
        $params[] = $classTypeId;
    }
    $sql .= ' ORDER BY cs.starts_at ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['confirmed_count'] = (int) $row['confirmed_count'];
        $row['spots_left'] = max(0, (int) $row['capacity'] - $row['confirmed_count']);
        $row['is_full'] = $row['spots_left'] <= 0;
    }
    return $rows;
}

/** Buduje URL kalendarza z podmienionym widokiem/datą, zachowując inne parametry GET. */
function calendar_href(string $basePath, string $view, DateTime $date, array $extra = []): string
{
    $params = array_filter(array_merge($extra, ['view' => $view, 'date' => date_param($date)]), fn($v) => $v !== null && $v !== '');
    return url($basePath . '?' . http_build_query($params));
}
