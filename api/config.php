<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';

// Підтримка OPTIONS preflight (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    header('Access-Control-Allow-Origin: https://dev.tinyeinstein.org');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

$db = getDB();

// Отримуємо конфіг: rooms, slots, staff, indicators
$rooms = $db->query(
    "SELECT room_code, room_name, is_admin
     FROM rooms
     WHERE active = 1
     ORDER BY room_code"
)->fetchAll();

$slots = $db->query(
    "SELECT slot_code, slot_label, slot_short, sort_order
     FROM slots
     WHERE active = 1
     ORDER BY sort_order"
)->fetchAll();

$staff = $db->query(
    "SELECT staff_id, staff_name, role, rooms
     FROM staff
     WHERE active = 1
     ORDER BY staff_name"
)->fetchAll();

$indicators = $db->query(
    "SELECT id, room_code, slot_code, scope, category, indicator_text, weight, sort_order
     FROM indicators
     WHERE active = 1
     ORDER BY scope, sort_order, id"
)->fetchAll();

// Конвертуємо числові поля з рядків в правильні типи
foreach ($rooms as &$r) {
    $r['is_admin'] = (int)$r['is_admin'];
}
foreach ($slots as &$s) {
    $s['sort_order'] = (int)$s['sort_order'];
}
foreach ($indicators as &$ind) {
    $ind['id']         = (int)$ind['id'];
    $ind['weight']     = (float)$ind['weight'];
    $ind['sort_order'] = (int)$ind['sort_order'];
}
unset($r, $s, $ind);

jsonOut([
    'ok'         => true,
    'rooms'      => $rooms,
    'slots'      => $slots,
    'staff'      => $staff,
    'indicators' => $indicators,
]);
