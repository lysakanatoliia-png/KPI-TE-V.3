<?php
/**
 * migrate/migrate_excel.php — імпорт з KPI.xlsx (Excel backup) в MySQL
 *
 * ВИМОГА: бібліотека PhpSpreadsheet
 *   composer require phpoffice/phpspreadsheet
 *   АБО: ручний CSV імпорт (дивись нижче)
 *
 * Розмісти KPI.xlsx поряд з цим скриптом.
 * Запусти: php migrate/migrate_excel.php
 *
 * АЛЬТЕРНАТИВА без Composer:
 *   1. Відкрити KPI.xlsx у Excel
 *   2. Зберегти аркуш FormData як CSV (UTF-8)
 *   3. Запустити: php migrate/migrate_excel.php --csv kpi_formdata.csv
 */

declare(strict_types=1);
require_once __DIR__.'/../api/db.php';

$useCsv = false;
$csvFile = '';

// Перевірити аргументи CLI
if (isset($argv)) {
    for ($i = 1; $i < count($argv); $i++) {
        if ($argv[$i] === '--csv' && isset($argv[$i+1])) {
            $useCsv = true;
            $csvFile = $argv[$i+1];
        }
    }
}

if ($useCsv) {
    migrateFromCsv($csvFile);
} else {
    migrateFromXlsx();
}

// ─────────────────────────────────────
// Міграція з CSV (рекомендовано якщо немає Composer)
// ─────────────────────────────────────
function migrateFromCsv(string $file): void {
    if (!file_exists($file)) {
        die("ERROR: CSV файл не знайдено: {$file}\n");
    }

    $handle = fopen($file, 'r');
    if (!$handle) die("ERROR: Не вдалося відкрити {$file}\n");

    $headers = fgetcsv($handle); // перший рядок — заголовки
    if (!$headers) die("ERROR: Порожній CSV\n");

    // Визначити індекси колонок (адаптуй до реальних заголовків Excel)
    $colMap = [];
    foreach ($headers as $i => $h) {
        $colMap[trim($h)] = $i;
    }
    echo "Колонки CSV: " . implode(', ', array_keys($colMap)) . "\n";

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
    $lineNum  = 1;

    try {
        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;
            if (empty(array_filter($row))) continue; // порожній рядок

            // АДАПТУЙ назви колонок до реального Excel
            $date    = normalizeDate(getCol($row, $colMap, 'Date', 'date', 'Timestamp') ?? '');
            $room    = getCol($row, $colMap, 'Room', 'room', 'Room Code') ?? '';
            $slot    = getCol($row, $colMap, 'Slot', 'slot', 'Time Slot') ?? '';
            $staffId = getCol($row, $colMap, 'StaffId', 'staff_id', 'Staff ID') ?? '';
            $staffId = $staffId ?: ('imported_' . md5($room . $date . $lineNum));
            $scope   = getCol($row, $colMap, 'Scope', 'scope', 'Type') ?? 'Team';
            $indText = getCol($row, $colMap, 'Indicator', 'indicator', 'KPI Indicator') ?? '';
            $category= getCol($row, $colMap, 'Category', 'category') ?? 'Imported';
            $checkRaw= getCol($row, $colMap, 'Value', 'Check', 'check', 'Result') ?? '1';
            $check   = ($checkRaw === '1' || strtolower($checkRaw) === 'yes') ? 1 : 0;
            $weight  = (float)(getCol($row, $colMap, 'Weight', 'weight') ?? 1.0);
            $earned  = $check ? $weight : 0.0;

            if (!$date || !$room) {
                echo "SKIP рядок {$lineNum}: немає дати або кімнати\n";
                $skipped++;
                continue;
            }

            // batch_id = day+room+slot (один batch на день+кімнату+слот)
            $batchId = 'XLS-' . md5("{$date}{$room}{$slot}");

            $stmt->execute([
                $batchId, $date, $room, $slot, $staffId, $scope,
                null, $indText, $category, $check, $weight, $earned, $weight,
                null, 'migrate_excel',
            ]);

            if ($stmt->rowCount() > 0) {
                $presenceStmt->execute([$batchId, $staffId]);
                $imported++;
            } else {
                $skipped++;
            }
        }

        $db->commit();
        auditLog($db, 'migrate_excel', '', $imported, "Imported from CSV");
        echo "Done. Imported: {$imported}, Skipped (duplicates/empty): {$skipped}\n";

    } catch (Throwable $e) {
        $db->rollBack();
        echo "ERROR рядок {$lineNum}: " . $e->getMessage() . "\n";
    } finally {
        fclose($handle);
    }
}

// ─────────────────────────────────────
// Міграція з XLSX (потребує PhpSpreadsheet)
// ─────────────────────────────────────
function migrateFromXlsx(): void {
    $xlsxFile = __DIR__ . '/KPI.xlsx';

    if (!file_exists($xlsxFile)) {
        die("ERROR: KPI.xlsx не знайдено. Розмісти файл поряд зі скриптом.\n"
           . "АБО: Використай CSV режим: php migrate_excel.php --csv kpi.csv\n");
    }

    // Перевірка чи є PhpSpreadsheet
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        die("ERROR: PhpSpreadsheet не встановлено.\n"
           . "Запусти: composer require phpoffice/phpspreadsheet\n"
           . "АБО використай CSV: php migrate_excel.php --csv kpi.csv\n");
    }

    require_once $autoload;

    echo "Читаю KPI.xlsx...\n";
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxFile);

    // Знайти аркуш FormData
    $sheetName = 'FormData';
    try {
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet) throw new Exception("Аркуш '{$sheetName}' не знайдено");
    } catch (Exception $e) {
        die("ERROR: " . $e->getMessage() . "\n");
    }

    // Конвертувати в масив і передати у CSV-логіку через temp-файл
    $rows = $sheet->toArray(null, true, true, false);
    $tmpFile = tempnam(sys_get_temp_dir(), 'kpi_migrate_');
    $fh = fopen($tmpFile, 'w');
    foreach ($rows as $row) {
        fputcsv($fh, $row);
    }
    fclose($fh);

    echo "Аркуш '{$sheetName}' прочитано: " . (count($rows) - 1) . " рядків\n";
    migrateFromCsv($tmpFile);
    unlink($tmpFile);
}

// ─────────────────────────────────────
// Допоміжні функції
// ─────────────────────────────────────

/** Отримати значення з рядка CSV по одному з можливих імен колонки. */
function getCol(array $row, array $colMap, string ...$names): ?string {
    foreach ($names as $name) {
        if (isset($colMap[$name]) && isset($row[$colMap[$name]])) {
            $val = trim((string)$row[$colMap[$name]]);
            if ($val !== '') return $val;
        }
    }
    return null;
}

/** Нормалізує дату в YYYY-MM-DD. */
function normalizeDate(string $d): string {
    $d = trim($d);
    if (empty($d)) return '';
    // MM/DD/YYYY або MM-DD-YYYY
    if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $d, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[1], (int)$m[2]);
    }
    // YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return $d;
    }
    // Excel серійний номер (дні з 1900-01-01)
    if (is_numeric($d) && (int)$d > 40000) {
        $ts = mktime(0,0,0,1,((int)$d - 25568),1970); // Excel epoch
        return date('Y-m-d', $ts);
    }
    return '';
}
