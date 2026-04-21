<?php
/**
 * migrate/migrate_v2.php — імпорт з kpi_history.json (V2) в MySQL
 *
 * Запустити через CLI або браузер ОДИН РАЗ:
 *   php migrate/migrate_v2.php
 *
 * Розмістити kpi_history.json поряд з цим скриптом або вказати PATH нижче.
 */

declare(strict_types=1);
require_once __DIR__.'/../api/db.php';

// Шлях до файлу kpi_history.json (скопіювати з сервера V2)
$historyFile = __DIR__ . '/kpi_history.json';

if (!file_exists($historyFile)) {
    die("ERROR: Файл kpi_history.json не знайдено за шляхом: {$historyFile}\n"
       . "Скопіюй файл з сервера V2 і розмісти поруч з цим скриптом.\n");
}

$history = json_decode(file_get_contents($historyFile), true);
if (!is_array($history)) {
    die("ERROR: Невалідний JSON у kpi_history.json\n");
}

echo "Записів у kpi_history.json: " . count($history) . "\n";

$db = getDB();
$db->beginTransaction();

$stmt = $db->prepare(
    "INSERT IGNORE INTO kpi_entries
     (batch_id, entry_date, room_code, slot_code, staff_id, scope,
      indicator_id, indicator_text, category, check_value, weight,
      earned_points, possible_points, comment, submitted_by)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);

$presenceStmt = $db->prepare(
    "INSERT IGNORE INTO kpi_batch_presence (batch_id, staff_id) VALUES (?,?)"
);

$imported = 0;
$skipped  = 0;

try {
    foreach ($history as $record) {
        // V2 формат: {batch_id, date, room, slot, staff_id, scope, indicator, category, check, weight}
        $batchId = $record['batch_id'] ?? $record['batchId'] ?? ('MIGR-' . md5(json_encode($record)));
        $date    = normalizeDate($record['date'] ?? $record['entry_date'] ?? '');

        if (!$date) {
            echo "SKIP: Невалідна дата у записі: " . json_encode($record) . "\n";
            $skipped++;
            continue;
        }

        $room    = $record['room']      ?? $record['room_code']  ?? '';
        $slot    = $record['slot']      ?? $record['slot_code']  ?? '';
        $staffId = $record['staff_id']  ?? $record['staffId']    ?? '';
        $scope   = $record['scope']     ?? 'Team';
        $indText = $record['indicator'] ?? $record['indicator_text'] ?? '';
        $category= $record['category']  ?? 'Imported';
        $check   = ($record['check'] === 'yes' || $record['check'] === 1 || $record['check_value'] === 1) ? 1 : 0;
        $weight  = (float)($record['weight'] ?? 1.0);
        $earned  = $check ? $weight : 0.0;
        $comment = $record['comment'] ?? null;

        $stmt->execute([
            $batchId, $date, $room, $slot, $staffId, $scope,
            null,    // indicator_id = NULL для мігрованих даних
            $indText, $category, $check, $weight, $earned, $weight,
            $comment ?: null, 'migrate_v2',
        ]);

        if ($stmt->rowCount() > 0) {
            $presenceStmt->execute([$batchId, $staffId]);
            $imported++;
        } else {
            $skipped++;
        }
    }

    $db->commit();
    auditLog($db, 'migrate_v2', '', $imported, "Imported from kpi_history.json");
    echo "Done. Imported: {$imported}, Skipped (duplicates): {$skipped}\n";

} catch (Throwable $e) {
    $db->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}

/**
 * Нормалізує дату в YYYY-MM-DD.
 * Підтримує: MM/DD/YYYY, MM-DD-YYYY, YYYY-MM-DD
 */
function normalizeDate(string $d): string {
    $d = trim($d);
    if (preg_match('/^(\d{2})[\/-](\d{2})[\/-](\d{4})$/', $d, $m)) {
        return "{$m[3]}-{$m[1]}-{$m[2]}";
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return $d;
    }
    return '';
}
