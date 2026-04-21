<?php
declare(strict_types=1);
require_once __DIR__.'/config.env.php';
date_default_timezone_set(TZ);

// Глобальний обробник виключень: повертає JSON, не HTML
set_exception_handler(function(Throwable $e): void {
    http_response_code(500);
    header('Content-Type: application/json');
    $msg = (APP_ENV === 'development') ? $e->getMessage() : 'Server error';
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
});

/**
 * Повертає singleton PDO-з'єднання з MySQL.
 * ATTR_EMULATE_PREPARES=false — обов'язково для bindValue з PDO::PARAM_INT у LIMIT.
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/**
 * Надсилає JSON-відповідь клієнту і завершує скрипт.
 * CORS лише для свого домену (не *).
 */
function jsonOut(array $d, int $s = 200): void {
    http_response_code($s);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: https://dev.tinyeinstein.org');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Відповідь з помилкою (не повертає — завершує скрипт). */
function jsonError(string $m, int $s = 400): never {
    jsonOut(['ok' => false, 'error' => $m], $s);
}

/** Читає тіло запиту як JSON, або повертає помилку 400. */
function getInput(): array {
    $d = json_decode(file_get_contents('php://input'), true);
    if (!is_array($d)) jsonError('Invalid JSON body');
    return $d;
}

/**
 * Нормалізує дату в YYYY-MM-DD.
 * Приймає MM-DD-YYYY (з форми) або YYYY-MM-DD.
 */
function sanitizeDate(string $d): string {
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $d, $m)) {
        return "{$m[3]}-{$m[1]}-{$m[2]}";
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return $d;
    }
    jsonError("Invalid date format: {$d}");
}

/** Повертає логін авторизованого менеджера (з HTTP Basic Auth). */
function getAuthUser(): string {
    return $_SERVER['PHP_AUTH_USER'] ?? 'anonymous';
}

/** Записує дію в таблицю audit_log. */
function auditLog(PDO $db, string $action, string $batchId = '', int $rows = 0, string $note = ''): void {
    $db->prepare(
        "INSERT INTO audit_log (user, action, batch_id, ip, rows_saved, note)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([
        getAuthUser(),
        $action,
        $batchId,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $rows,
        $note ?: null,
    ]);
}
