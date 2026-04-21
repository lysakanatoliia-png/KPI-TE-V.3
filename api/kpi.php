<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';

// OPTIONS preflight для CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    header('Access-Control-Allow-Origin: https://dev.tinyeinstein.org');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

$action = $_GET['action'] ?? '';

match($action) {
    'saveBatch'      => saveBatch(),
    'checkDuplicate' => checkDuplicate(),
    default          => jsonError('Unknown action', 404),
};

/**
 * POST /api/kpi.php?action=saveBatch
 *
 * Приймає весь batch одним запитом і зберігає в одній транзакції.
 * Тіло запиту:
 * {
 *   "batch_id":          "B-1776634605910",
 *   "date":              "04-20-2026",
 *   "room_code":         "Pink",
 *   "slot_code":         "09AM_11AM",
 *   "present_staff_ids": ["st_tamar", "st_alla"],
 *   "team_items": [
 *     { "indicator_id": 5, "check": "yes", "comment": "" }
 *   ],
 *   "individual_items": {
 *     "st_tamar": [{ "indicator_id": 22, "check": "no", "comment": "late" }]
 *   }
 * }
 */
function saveBatch(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('POST required', 405);
    }

    $in      = getInput();
    $db      = getDB();
    $batchId = trim($in['batch_id']  ?? '') ?: jsonError('Missing batch_id');
    $date    = sanitizeDate($in['date']    ?? jsonError('Missing date'));
    $room    = trim($in['room_code']  ?? '') ?: jsonError('Missing room_code');
    $slot    = trim($in['slot_code']  ?? '') ?: jsonError('Missing slot_code');
    $staffIds  = is_array($in['present_staff_ids'] ?? null) ? $in['present_staff_ids'] : [];
    $teamItems = is_array($in['team_items'] ?? null) ? $in['team_items'] : [];
    $indItems  = is_array($in['individual_items'] ?? null) ? $in['individual_items'] : [];
    $by        = getAuthUser();

    // Завантажити snapshot усіх індикаторів для цієї кімнати+слоту
    $indSnap = loadIndicatorSnapshots($db, $room, $slot);

    $entryStmt = $db->prepare(
        "INSERT IGNORE INTO kpi_entries
         (batch_id, entry_date, room_code, slot_code, staff_id, scope,
          indicator_id, indicator_text, category, check_value, weight,
          earned_points, possible_points, comment, submitted_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );

    $presenceStmt = $db->prepare(
        "INSERT IGNORE INTO kpi_batch_presence (batch_id, staff_id) VALUES (?,?)"
    );

    $saved = 0;
    $db->beginTransaction();

    try {
        // 1. Записати хто присутній у batch
        foreach ($staffIds as $sid) {
            if (is_string($sid) && $sid !== '') {
                $presenceStmt->execute([$batchId, $sid]);
            }
        }

        // 2. Team items — кожен team-індикатор записується для кожного присутнього стафу
        foreach ($teamItems as $item) {
            $snap = $indSnap[(int)($item['indicator_id'] ?? 0)] ?? null;
            if (!$snap) continue;
            $check  = ($item['check'] === 'yes') ? 1 : 0;
            $earned = $check ? (float)$snap['weight'] : 0.0;
            $comment = trim($item['comment'] ?? '') ?: null;

            foreach ($staffIds as $staffId) {
                if (!is_string($staffId) || $staffId === '') continue;
                $entryStmt->execute([
                    $batchId, $date, $room, $slot, $staffId, 'Team',
                    (int)$snap['id'], $snap['indicator_text'], $snap['category'],
                    $check, (float)$snap['weight'], $earned, (float)$snap['weight'],
                    $comment, $by,
                ]);
                $saved += $entryStmt->rowCount();
            }
        }

        // 3. Individual items — кожен стаф має свій список
        foreach ($indItems as $staffId => $items) {
            if (!is_string($staffId) || $staffId === '' || !is_array($items)) continue;
            foreach ($items as $item) {
                $snap = $indSnap[(int)($item['indicator_id'] ?? 0)] ?? null;
                if (!$snap) continue;
                $check  = ($item['check'] === 'yes') ? 1 : 0;
                $earned = $check ? (float)$snap['weight'] : 0.0;
                $comment = trim($item['comment'] ?? '') ?: null;

                $entryStmt->execute([
                    $batchId, $date, $room, $slot, $staffId, 'Individual',
                    (int)$snap['id'], $snap['indicator_text'], $snap['category'],
                    $check, (float)$snap['weight'], $earned, (float)$snap['weight'],
                    $comment, $by,
                ]);
                $saved += $entryStmt->rowCount();
            }
        }

        $db->commit();
        auditLog($db, 'saveBatch', $batchId, $saved);
        jsonOut(['ok' => true, 'saved' => $saved]);

    } catch (Throwable $e) {
        $db->rollBack();
        // В production не розкриваємо деталі помилки БД
        $msg = (APP_ENV === 'development') ? $e->getMessage() : 'DB error';
        jsonError($msg, 500);
    }
}

/**
 * GET /api/kpi.php?action=checkDuplicate&batch_id=...
 *
 * UX-ендпоінт: показує попередження якщо слот вже збережено.
 * НЕ є захистом від дублікатів — це роль UNIQUE KEY в БД.
 */
function checkDuplicate(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError('GET required', 405);
    }

    $batchId = trim($_GET['batch_id'] ?? '') ?: jsonError('Missing batch_id');
    $db = getDB();

    $stmt = $db->prepare(
        "SELECT COUNT(*) as cnt FROM kpi_entries WHERE batch_id = ? LIMIT 1"
    );
    $stmt->execute([$batchId]);
    $row = $stmt->fetch();

    jsonOut(['ok' => true, 'exists' => ((int)$row['cnt'] > 0)]);
}

/**
 * Завантажує snapshot індикаторів з БД для заданої кімнати + слоту.
 * Повертає map [indicator_id => row].
 * '*' = індикатор підходить для всіх кімнат або всіх слотів.
 */
function loadIndicatorSnapshots(PDO $db, string $room, string $slot): array {
    $stmt = $db->prepare(
        "SELECT id, indicator_text, category, weight, scope
         FROM indicators
         WHERE (room_code = ? OR room_code = '*')
           AND (slot_code = ? OR slot_code = '*')
           AND active = 1"
    );
    $stmt->execute([$room, $slot]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $map[(int)$r['id']] = $r;
    }
    return $map;
}
