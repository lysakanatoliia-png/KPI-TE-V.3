<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    header('Access-Control-Allow-Origin: https://dev.tinyeinstein.org');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('GET required', 405);
}

$action = $_GET['action'] ?? '';

match($action) {
    'summary'    => getSummary(),
    'violations' => getViolations(),
    'daily'      => getDaily(),
    'monthly'    => getMonthly(),
    default      => jsonError('Unknown action', 404),
};

/**
 * GET ?action=summary&from=2026-04-01&to=2026-04-30[&room=Pink][&page=1&per_page=50]
 *
 * Повертає KPI% по кожному staff_id за заданий період.
 * TotalKPI% = Team×0.8 + Ind×0.2 (або Team×1.0 якщо Admin або IndPossible=0)
 */
function getSummary(): void {
    $db   = getDB();
    $from = sanitizeDate($_GET['from'] ?? jsonError('Missing from'));
    $to   = sanitizeDate($_GET['to']   ?? jsonError('Missing to'));
    $room = trim($_GET['room'] ?? '');

    $page    = max(1, (int)($_GET['page']     ?? 1));
    $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 50)));

    // Будуємо умови WHERE
    $where  = "ke.entry_date BETWEEN :from AND :to";
    $params = [':from' => $from, ':to' => $to];

    if ($room !== '') {
        $where .= " AND ke.room_code = :room";
        $params[':room'] = $room;
    }

    // Рахуємо Team KPI та Individual KPI окремо, потім об'єднуємо
    $sql = "
        SELECT
            ke.staff_id,
            st.staff_name,
            ke.room_code,
            r.is_admin,
            SUM(CASE WHEN ke.scope='Team'       THEN ke.earned_points   ELSE 0 END) AS team_earned,
            SUM(CASE WHEN ke.scope='Team'       THEN ke.possible_points ELSE 0 END) AS team_possible,
            SUM(CASE WHEN ke.scope='Individual' THEN ke.earned_points   ELSE 0 END) AS ind_earned,
            SUM(CASE WHEN ke.scope='Individual' THEN ke.possible_points ELSE 0 END) AS ind_possible
        FROM kpi_entries ke
        LEFT JOIN staff st ON st.staff_id = ke.staff_id
        LEFT JOIN rooms r  ON r.room_code = ke.room_code
        WHERE {$where}
        GROUP BY ke.staff_id, ke.room_code
        ORDER BY ke.room_code, st.staff_name
        LIMIT :lim OFFSET :off
    ";

    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    // LIMIT і OFFSET — обов'язково PARAM_INT при EMULATE_PREPARES=false
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Обчислюємо KPI% і TotalKPI%
    foreach ($rows as &$r) {
        $teamP = (float)$r['team_possible'];
        $indP  = (float)$r['ind_possible'];

        $team = $teamP > 0 ? round((float)$r['team_earned'] / $teamP * 100, 2) : 0.0;
        $ind  = $indP  > 0 ? round((float)$r['ind_earned']  / $indP  * 100, 2) : 0.0;

        $r['team_kpi'] = $team;
        $r['ind_kpi']  = $ind;

        // TotalKPI: Admin або без Individual → тільки Team
        if ((int)$r['is_admin'] === 1 || $indP == 0) {
            $r['total_kpi'] = round($team, 2);
        } else {
            $r['total_kpi'] = round($team * 0.8 + $ind * 0.2, 2);
        }

        // Прибираємо зайві поля з відповіді
        unset($r['team_earned'], $r['team_possible'], $r['ind_earned'], $r['ind_possible']);
    }
    unset($r);

    jsonOut(['ok' => true, 'data' => $rows, 'page' => $page, 'per_page' => $perPage]);
}

/**
 * GET ?action=violations&from=2026-04-01&to=2026-04-30[&room=Pink][&limit=20]
 *
 * Топ індикаторів з найбільшою кількістю check=0 (порушень).
 */
function getViolations(): void {
    $db   = getDB();
    $from = sanitizeDate($_GET['from'] ?? jsonError('Missing from'));
    $to   = sanitizeDate($_GET['to']   ?? jsonError('Missing to'));
    $room  = trim($_GET['room'] ?? '');
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

    $where  = "entry_date BETWEEN :from AND :to AND check_value = 0";
    $params = [':from' => $from, ':to' => $to];

    if ($room !== '') {
        $where .= " AND room_code = :room";
        $params[':room'] = $room;
    }

    $stmt = $db->prepare("
        SELECT indicator_text, category, scope, COUNT(*) AS violation_count
        FROM kpi_entries
        WHERE {$where}
        GROUP BY indicator_text, category, scope
        ORDER BY violation_count DESC
        LIMIT :lim
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    jsonOut(['ok' => true, 'data' => $stmt->fetchAll()]);
}

/**
 * GET ?action=daily&date=2026-04-20&room=Pink
 *
 * Всі записи за один день для кімнати (для перегляду і редагування).
 */
function getDaily(): void {
    $db   = getDB();
    $date = sanitizeDate($_GET['date'] ?? jsonError('Missing date'));
    $room = trim($_GET['room'] ?? '') ?: jsonError('Missing room');

    $stmt = $db->prepare("
        SELECT
            ke.id, ke.batch_id, ke.slot_code, ke.staff_id,
            st.staff_name, ke.scope, ke.indicator_text, ke.category,
            ke.check_value, ke.weight, ke.earned_points, ke.possible_points,
            ke.comment, ke.submitted_by, ke.created_at
        FROM kpi_entries ke
        LEFT JOIN staff st ON st.staff_id = ke.staff_id
        WHERE ke.entry_date = :date AND ke.room_code = :room
        ORDER BY ke.slot_code, ke.scope, st.staff_name
    ");
    $stmt->execute([':date' => $date, ':room' => $room]);

    jsonOut(['ok' => true, 'date' => $date, 'room' => $room, 'data' => $stmt->fetchAll()]);
}

/**
 * GET ?action=monthly&year=2026&month=4[&room=Pink]
 *
 * Місячний зведений KPI по кімнатах і стафу.
 */
function getMonthly(): void {
    $db    = getDB();
    $year  = (int)($_GET['year']  ?? 0) ?: jsonError('Missing year');
    $month = (int)($_GET['month'] ?? 0) ?: jsonError('Missing month');
    $room  = trim($_GET['room'] ?? '');

    // Перший і останній день місяця
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to   = date('Y-m-t', strtotime($from));

    $where  = "ke.entry_date BETWEEN :from AND :to";
    $params = [':from' => $from, ':to' => $to];

    if ($room !== '') {
        $where .= " AND ke.room_code = :room";
        $params[':room'] = $room;
    }

    $stmt = $db->prepare("
        SELECT
            ke.staff_id,
            st.staff_name,
            ke.room_code,
            r.is_admin,
            SUM(CASE WHEN ke.scope='Team'       THEN ke.earned_points   ELSE 0 END) AS team_earned,
            SUM(CASE WHEN ke.scope='Team'       THEN ke.possible_points ELSE 0 END) AS team_possible,
            SUM(CASE WHEN ke.scope='Individual' THEN ke.earned_points   ELSE 0 END) AS ind_earned,
            SUM(CASE WHEN ke.scope='Individual' THEN ke.possible_points ELSE 0 END) AS ind_possible,
            COUNT(DISTINCT ke.batch_id) AS total_batches
        FROM kpi_entries ke
        LEFT JOIN staff st ON st.staff_id = ke.staff_id
        LEFT JOIN rooms r  ON r.room_code = ke.room_code
        WHERE {$where}
        GROUP BY ke.staff_id, ke.room_code
        ORDER BY ke.room_code, st.staff_name
    ");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $teamP = (float)$r['team_possible'];
        $indP  = (float)$r['ind_possible'];

        $team = $teamP > 0 ? round((float)$r['team_earned'] / $teamP * 100, 2) : 0.0;
        $ind  = $indP  > 0 ? round((float)$r['ind_earned']  / $indP  * 100, 2) : 0.0;

        $r['team_kpi'] = $team;
        $r['ind_kpi']  = $ind;

        if ((int)$r['is_admin'] === 1 || $indP == 0) {
            $r['total_kpi'] = round($team, 2);
        } else {
            $r['total_kpi'] = round($team * 0.8 + $ind * 0.2, 2);
        }

        $r['total_batches'] = (int)$r['total_batches'];
        unset($r['team_earned'], $r['team_possible'], $r['ind_earned'], $r['ind_possible']);
    }
    unset($r);

    jsonOut([
        'ok'    => true,
        'year'  => $year,
        'month' => $month,
        'from'  => $from,
        'to'    => $to,
        'data'  => $rows,
    ]);
}
