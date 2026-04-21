<?php
/**
 * migrate/seed_config.php
 * Заповнює БД реальними даними конфігурації з V2.
 * Запустити ОДИН РАЗ після install.sql:
 *   php migrate/seed_config.php
 *   АБО відкрити в браузері: https://kpi.tinyeinstein.org/migrate/seed_config.php
 * Після запуску — заблокувати папку migrate/ у .htaccess.
 */

declare(strict_types=1);
require_once __DIR__.'/../api/db.php';

$db = getDB();
$db->beginTransaction();

try {

// ─────────────────────────────────────
// ROOMS
// ─────────────────────────────────────
$stmtRoom = $db->prepare(
    "INSERT IGNORE INTO rooms (room_code, room_name, is_admin, active) VALUES (?,?,?,?)"
);
$rooms = [
    ['Pink',  'Pink Room',  0, 1],
    ['Green', 'Green Room', 0, 1],
    ['Blue',  'Blue Room',  0, 1],
    ['Admin', 'Admin',      1, 1],
];
foreach ($rooms as $r) $stmtRoom->execute($r);
echo "Rooms: " . count($rooms) . " rows\n";

// ─────────────────────────────────────
// SLOTS
// ─────────────────────────────────────
$stmtSlot = $db->prepare(
    "INSERT IGNORE INTO slots (slot_code, slot_label, slot_short, sort_order, active) VALUES (?,?,?,?,?)"
);
$slots = [
    ['09AM_11AM',  '9:00-11:00 AM',  '9–11 AM',   1, 1],
    ['11AM_01PM',  '11:00-1:00 PM',  '11am–1pm',  2, 1],
    ['01PM_03PM',  '1:00-3:00 PM',   '1–3 PM',    3, 1],
    ['03PM_06PM',  '3:00-6:00 PM',   '3–6 PM',    4, 1],
    ['ONCE_DAY',   'Once a day',     'Daily',      5, 1],
    ['ONCE_MONTH', 'Once a month',   'Monthly',   6, 1],
    ['SEASONAL',   'Seasonal',       'Seasonal',  7, 1],
];
foreach ($slots as $s) $stmtSlot->execute($s);
echo "Slots: " . count($slots) . " rows\n";

// ─────────────────────────────────────
// STAFF (видалено порожні рядки і test)
// ─────────────────────────────────────
$stmtStaff = $db->prepare(
    "INSERT IGNORE INTO staff (staff_id, staff_name, role, rooms, active) VALUES (?,?,?,?,?)"
);
$staff = [
    ['st_meryem',  'MERYEM YILMAZ',            '',               'Green', 1],
    ['st_lilya',   'LILYA FILATOVA',            'Assistant',      'Blue',  1],
    ['st_natalia', 'NATALIA GOLDMAN',           '',               'Green', 1],
    ['st_tamar',   'TAMAR SEKHNIASHVILI',       'Assistant',      'Pink',  1],
    ['st_alla',    'ALLA HRYTSENKO',            'Master teacher', 'Pink',  0], // Active: ""
    ['st_malahat', 'MALAHAT JAFAROVA',          'Floater',        'Pink',  1],
    ['st_filiz',   'FILIZ ENGEZ',               'Teacher',        'Pink',  1],
    ['st_admin',   'STEPHANIE DA SILVA DE MELO','Office',         'Admin', 1],
];
foreach ($staff as $s) $stmtStaff->execute($s);
echo "Staff: " . count($staff) . " rows\n";

// ─────────────────────────────────────
// INDICATORS — всі з config_indicators.json
// Формат: [room_code, slot_code, scope, category, indicator_text, weight, sort_order, active]
// ─────────────────────────────────────
$stmtInd = $db->prepare(
    "INSERT IGNORE INTO indicators
     (room_code, slot_code, scope, category, indicator_text, weight, sort_order, active)
     VALUES (?,?,?,?,?,?,?,?)"
);

$indicators = [

// ═══════════════════════════════════════
// BLUE — 09AM_11AM — Team
// ═══════════════════════════════════════
['Blue','09AM_11AM','Team','Environment','Is the classroom temperature comfortable and is the air fresh?',1,1,1],
['Blue','09AM_11AM','Team','Environment','Cleanliness of the classroom upon entry.',2,2,1],
['Blue','09AM_11AM','Team','Environment','Positive classroom atmosphere:children are engaged, well behaved. Teachers are happy',1,3,1],
['Blue','09AM_11AM','Team','Environment','TE bags are checked & sorted (change of clothes, bottles, lunch bags, diapers, toys, etc)',2,4,1],
['Blue','09AM_11AM','Team','Hygiene & Care','Bottles are counted (!) and filled with water.',3,6,1],
['Blue','09AM_11AM','Team','Hygiene & Care','Tables are properly sanitized 2 min before kids',2,7,1],
['Blue','09AM_11AM','Team','Hygiene & Care','Check the cleanliness of the bathrooms and the room.',2,8,1],
['Blue','09AM_11AM','Team','Hygiene & Care','Tables and under tables are clean after meals.',1,9,1],
['Blue','09AM_11AM','Team','Hygiene & Care','Check kitchenette, cubbies, dishwasher and plates/utensils for cleanliness',1,10,1],
['Blue','09AM_11AM','Team','Safety & Ratio','Compliance with the RATIO.',5,11,1],
['Blue','09AM_11AM','Team','Safety & Ratio','Roll Call. Check Brightwheel for check-ins',2,12,1],
['Blue','09AM_11AM','Team','Safety & Ratio','Compliance with ZONING (during outdoor time).',2,13,1],
['Blue','09AM_11AM','Team','Safety & Ratio','Children\'s allergies and dietary preferences are taken into account and followed.',3,14,0],

// BLUE — 11AM_01PM — Team
['Blue','11AM_01PM','Team','Hygiene & Care','Tables are clean after meals.',1,1,1],
['Blue','11AM_01PM','Team','Safety & Ratio','Compliance with the RATIO.',5,2,1],

// BLUE — 01PM_03PM — Team
['Blue','01PM_03PM','Team','Safety & Ratio','Kids are 100% seen during nap, they are all lying down quietly, bathroom break supervised',3,1,1],
['Blue','01PM_03PM','Team','Hygiene & Care','Check kitchenette: food stored properly, pots washed. cubbinets, dishwasher and plates/utensils are clean',1,2,1],

// BLUE — 03PM_06PM — Team
['Blue','03PM_06PM','Team','Professional Knowledge & Execution','Organizing and supervising special events: shows, field trips, special guests, extravaganzas',3,1,1],
['Blue','03PM_06PM','Team','Professional Knowledge & Execution','Communicate child\'s needs and unusual situations with the parents and admins',3,2,1],
['Blue','03PM_06PM','Team','Professional Knowledge & Execution','The classroom is prepared for the weekly theme (bookcase, bulletin board, tabletop, décor).',1,3,1],
['Blue','03PM_06PM','Team','Environment','Has the trash been taken out? Floors are clean on the days without janitor',2,4,1],
['Blue','03PM_06PM','Team','Environment','Toys are put away from the playground / wood chips are cleared.Rooster has food and water',1,5,1],
['Blue','03PM_06PM','Team','Environment','Check for forgotten items and bring them inside/ water bottles washed and empty',1,6,1],
['Blue','03PM_06PM','Team','Environment','Closing procedure is done:AC off, trash is out, food stored, laundry is dry, doors locked',2,7,1],
['Blue','03PM_06PM','Team','Hygiene & Care','Check if the children have been given snacks',3,8,1],
['Blue','03PM_06PM','Team','Hygiene & Care','Check the cleanliness of the bathrooms and the room.',2,9,1],
['Blue','03PM_06PM','Team','Hygiene & Care','Tables and under tables are clean after meals.',1,10,1],
['Blue','03PM_06PM','Team','Hygiene & Care','Water bottles are washed and filled.',2,11,1],
['Blue','03PM_06PM','Team','Hygiene & Care','Check if the children are brushed. Sunscreen\'s on during sun season',1,12,1],
['Blue','03PM_06PM','Team','Hygiene & Care','Check if the children have been changed into clean clothes after nap time.',1,13,1],
['Blue','03PM_06PM','Team','Hygiene & Care','Timely toileting/ diaper change and mandatory water breaks',2,14,1],
['Blue','03PM_06PM','Team','Hygiene & Care','Check sun hats and appropriate clothing (in winter, ensure outerwear is zipped and hats on).',2,15,1],
['Blue','03PM_06PM','Team','Safety & Ratio','Compliance with the RATIO.',5,16,1],
['Blue','03PM_06PM','Team','Safety & Ratio','Compliance with ZONING (during outdoor time).',1,17,1],
['Blue','03PM_06PM','Team','Safety & Ratio','Children\'s allergies and dietary preferences are taken into account and followed.',3,18,1],

// BLUE — ONCE_DAY — Team
['Blue','ONCE_DAY','Team','Classroom organization','Beds, boxes, shelves, hooks, and diapers are labeled.',3,1,1],

// BLUE — ONCE_MONTH — Team
['Blue','ONCE_MONTH','Team','Classroom organization','Has the bulletin board, Family Corner, and Birthday Corner been updated?',2,1,1],

// BLUE — SEASONAL — Team
['Blue','SEASONAL','Team','Classroom organization','Active participation in decorating rooms and spaces before holidays.',2,1,1],

// BLUE — Individual (SlotCode = *)
['Blue','*','Individual','Individual','Timely check‑in & check‑out /Punctuality (Be ready on time)',1,1,1],
['Blue','*','Individual','Individual','Code of conduct',1,2,1],
['Blue','*','Individual','Individual','Addressing accidents/incidents properly (reporting to admins/parents / talk to child / circle time)',4,3,1],
['Blue','*','Individual','Individual','Lesson plans are prepared every Thursday (for the following week).',2,4,1],
['Blue','*','Individual','Individual','Copies and materials are prepared before the start of the lesson.',2,5,1],
['Blue','*','Individual','Individual','Circle time has visual props, posters, books and games, including some physical break every 15 min',3,6,1],
['Blue','*','Individual','Individual','Unauthorized Personal cell phone use during work',5,7,1],
['Blue','*','Individual','Individual','Art & craft is colorful, theme related and age appropriate',3,8,1],
['Blue','*','Individual','Individual','Music and movement are theme related and conducted daily 10-20 min. Once a week 30min',2,9,1],
['Blue','*','Individual','Individual','Timely toileting/clothes change and mandatory water breaks',3,10,1],
['Blue','*','Individual','Individual','Nap time protocol met: 100% supervision, bathroom, kids lying down quietly',2,11,1],
['Blue','*','Individual','Individual','Kind and friendly behavior with children, parents and admins',2,12,1],
['Blue','*','Individual','Individual','New student check list:communication,photos, personal space (cubby, hooks, Friday folder, bed,etc)',4,13,1],
['Blue','*','Individual','Individual','Preparation for special events (daily rehearsals, props, costumes, decoration, communication with the parents)',3,14,1],
['Blue','*','Individual','Individual','Preparing evaluations. Conducting parent-teacher conference',3,15,1],
['Blue','*','Individual','Individual','Organizing and supervising special events: shows, field trips, special guests, extravaganzas',3,16,1],
['Blue','*','Individual','Individual','Morning exercise routine is varied every day. P.E. prepared once week.',2,17,1],
['Blue','*','Individual','Individual','Friendly and positive work attitude',2,18,1],
['Blue','*','Individual','Individual','Daily academic study: phonics, math, worksheets',1,19,1],
['Blue','*','Individual','Individual','Parking Zone Check.',1,20,1],

// ═══════════════════════════════════════
// GREEN — 09AM_11AM — Team
// ═══════════════════════════════════════
['Green','09AM_11AM','Team','Environment','Is the classroom temperature comfortable and is the air fresh?',1,1,1],
['Green','09AM_11AM','Team','Environment','Cleanliness of the classroom upon entry.',2,2,1],
['Green','09AM_11AM','Team','Environment','Positive classroom atmosphere:children are engaged, well behaved. Teachers are happy',1,3,1],
['Green','09AM_11AM','Team','Environment','TE bags are checked & sorted (change of clothes, bottles, lunch bags, diapers, toys, etc)',2,4,1],
['Green','09AM_11AM','Team','Hygiene & Care','Bottles are counted (!) and filled with water.',3,6,1],
['Green','09AM_11AM','Team','Hygiene & Care','Tables are properly sanitized 2 min before kids',2,7,1],
['Green','09AM_11AM','Team','Hygiene & Care','Check the cleanliness of the bathrooms and the room.',2,8,1],
['Green','09AM_11AM','Team','Hygiene & Care','Tables and under tables are clean after meals.',1,9,1],
['Green','09AM_11AM','Team','Hygiene & Care','Check kitchenette, cubbies, dishwasher and plates/utensils for cleanliness',1,10,1],
['Green','09AM_11AM','Team','Safety & Ratio','Compliance with the RATIO.',5,11,1],
['Green','09AM_11AM','Team','Safety & Ratio','Roll Call. Check Brightwheel for check-ins',2,12,1],
['Green','09AM_11AM','Team','Safety & Ratio','Compliance with ZONING (during outdoor time).',2,13,1],
['Green','09AM_11AM','Team','Safety & Ratio','Children\'s allergies and dietary preferences are taken into account and followed.',3,14,0],

// GREEN — 11AM_01PM — Team
['Green','11AM_01PM','Team','Hygiene & Care','Tables are clean after meals.',1,1,1],
['Green','11AM_01PM','Team','Safety & Ratio','Compliance with the RATIO.',5,2,1],

// GREEN — 01PM_03PM — Team
['Green','01PM_03PM','Team','Safety & Ratio','Kids are 100% seen during nap, they are all lying down quietly, bathroom break supervised',3,1,1],
['Green','01PM_03PM','Team','Hygiene & Care','Check kitchenette: food stored properly, pots washed. cubbinets, dishwasher and plates/utensils are clean',1,2,1],

// GREEN — 03PM_06PM — Team
['Green','03PM_06PM','Team','Professional Knowledge & Execution','Organizing and supervising special events: shows, field trips, special guests, extravaganzas',3,1,1],
['Green','03PM_06PM','Team','Professional Knowledge & Execution','Communicate child\'s needs and unusual situations with the parents and admins',3,2,1],
['Green','03PM_06PM','Team','Professional Knowledge & Execution','The classroom is prepared for the weekly theme (bookcase, bulletin board, tabletop, décor).',1,3,1],
['Green','03PM_06PM','Team','Environment','Has the trash been taken out? Floors are clean on the days without janitor',2,4,1],
['Green','03PM_06PM','Team','Environment','Toys are put away from the playground / wood chips are cleared.Rooster has food and water',1,5,1],
['Green','03PM_06PM','Team','Environment','Check for forgotten items and bring them inside/ water bottles washed and empty',1,6,1],
['Green','03PM_06PM','Team','Environment','Closing procedure is done:AC off, trash is out, food stored, laundry is dry, doors locked',2,7,1],
['Green','03PM_06PM','Team','Hygiene & Care','Check if the children have been given snacks',3,8,1],
['Green','03PM_06PM','Team','Hygiene & Care','Check the cleanliness of the bathrooms and the room.',2,9,1],
['Green','03PM_06PM','Team','Hygiene & Care','Tables and under tables are clean after meals.',1,10,1],
['Green','03PM_06PM','Team','Hygiene & Care','Water bottles are washed and filled.',2,11,1],
['Green','03PM_06PM','Team','Hygiene & Care','Check if the children are brushed. Sunscreen\'s on during sun season',1,12,1],
['Green','03PM_06PM','Team','Hygiene & Care','Check if the children have been changed into clean clothes after nap time.',1,13,1],
['Green','03PM_06PM','Team','Hygiene & Care','Timely toileting/ diaper change and mandatory water breaks',2,14,1],
['Green','03PM_06PM','Team','Hygiene & Care','Check sun hats and appropriate clothing (in winter, ensure outerwear is zipped and hats on).',2,15,1],
['Green','03PM_06PM','Team','Safety & Ratio','Compliance with the RATIO.',5,16,1],
['Green','03PM_06PM','Team','Safety & Ratio','Compliance with ZONING (during outdoor time).',1,17,1],
['Green','03PM_06PM','Team','Safety & Ratio','Children\'s allergies and dietary preferences are taken into account and followed.',3,18,1],

// GREEN — ONCE_DAY / ONCE_MONTH / SEASONAL
['Green','ONCE_DAY','Team','Classroom organization','Beds, boxes, shelves, hooks, and diapers are labeled.',3,1,1],
['Green','ONCE_MONTH','Team','Classroom organization','Has the bulletin board, Family Corner, and Birthday Corner been updated?',2,1,1],
['Green','SEASONAL','Team','Classroom organization','Active participation in decorating rooms and spaces before holidays.',2,1,1],

// GREEN — Individual
['Green','*','Individual','Individual','Timely check‑in & check‑out /Punctuality (Be ready on time)',1,1,1],
['Green','*','Individual','Individual','Code of conduct',1,2,1],
['Green','*','Individual','Individual','Addressing accidents/incidents properly (reporting to admins/parents / talk to child / circle time)',4,3,1],
['Green','*','Individual','Individual','Lesson plans are prepared every Thursday (for the following week).',2,4,1],
['Green','*','Individual','Individual','Copies and materials are prepared before the start of the lesson.',2,5,1],
['Green','*','Individual','Individual','Circle time has visual props, posters, books and games, including some physical break every 15 min',3,6,1],
['Green','*','Individual','Individual','Unauthorized Personal cell phone use during work',5,7,1],
['Green','*','Individual','Individual','Art & craft is colorful, theme related and age appropriate',3,8,1],
['Green','*','Individual','Individual','Music and movement are theme related and conducted daily 10-20 min. Once a week 30min',2,9,1],
['Green','*','Individual','Individual','Timely toileting/ diaper/clothes change and mandatory water breaks',3,10,1],
['Green','*','Individual','Individual','Nap time protocol met: 100% supervision, bathroom, kids lying down quietly',2,11,1],
['Green','*','Individual','Individual','Kind and friendly behavior with children, parents and admins',2,12,1],
['Green','*','Individual','Individual','New student check list:communication,photos, personal space (cubby, hooks, Friday folder, bed,etc)',4,13,1],
['Green','*','Individual','Individual','Preparation for special events (daily rehearsals, props, costumes, decoration, communication with the parents)',3,14,1],
['Green','*','Individual','Individual','Preparing evaluations. Conducting parent-teacher conference',3,15,1],
['Green','*','Individual','Individual','Organizing and supervising special events: shows, field trips, special guests, extravaganzas',3,16,1],
['Green','*','Individual','Individual','Morning exercise routine is varied every day. P.E. prepared once week.',2,17,1],
['Green','*','Individual','Individual','Friendly and positive work attitude',2,18,1],
['Green','*','Individual','Individual','Parking Zone Check.',1,19,1],

// ═══════════════════════════════════════
// PINK — 09AM_11AM — Team
// ═══════════════════════════════════════
['Pink','09AM_11AM','Team','Professional Knowledge & Execution','The classroom is prepared for the weekly theme (bookcase, bulletin board, tabletop, décor).',2,1,1],
['Pink','09AM_11AM','Team','Environment','Is the classroom temperature comfortable and is the air fresh?',1,2,1],
['Pink','09AM_11AM','Team','Environment','Cleanliness of the classroom upon entry.',1,3,1],
['Pink','09AM_11AM','Team','Environment','TE bags are checked & sorted (change of clothes, bottles, lunch bags, diapers, toys, etc)',2,4,1],
['Pink','09AM_11AM','Team','Environment','Positive classroom atmosphere:children are engaged and well behaved. Teachers are happy',1,5,1],
['Pink','09AM_11AM','Team','Hygiene & Care','Bottles are counted and filled with water.',2,6,1],
['Pink','09AM_11AM','Team','Hygiene & Care','Check the cleanliness of the bathrooms and the room.',2,7,1],
['Pink','09AM_11AM','Team','Hygiene & Care','Tables are disinfected properly and 2 min before kids.',2,8,1],
['Pink','09AM_11AM','Team','Hygiene & Care','Tables & under tables are clean after meals.',1,9,1],
['Pink','09AM_11AM','Team','Hygiene & Care','The children\'s diapers are changed / bags with soiled clothes are labeled.',4,10,1],
['Pink','09AM_11AM','Team','Hygiene & Care','Check kitchenette, cubbies, dishwasher and plates/utensils for cleanliness.',1,11,1],
['Pink','09AM_11AM','Team','Safety & Ratio','Compliance with the RATIO.',5,12,1],
['Pink','09AM_11AM','Team','Safety & Ratio','Roll Call. Check Brightwheel for check-ins.',2,13,1],
['Pink','09AM_11AM','Team','Safety & Ratio','Compliance with ZONING (during outdoor time).',1,14,1],
['Pink','09AM_11AM','Team','Safety & Ratio','Children\'s allergies and dietary preferences are taken into account and followed.',3,15,1],

// PINK — 11AM_01PM — Team
['Pink','11AM_01PM','Team','Hygiene & Care','Tables and under tables are clean after meals.',1,1,1],
['Pink','11AM_01PM','Team','Hygiene & Care','The children\'s diapers are changed / bags with soiled clothes are labeled.',4,2,1],
['Pink','11AM_01PM','Team','Safety & Ratio','Compliance with the RATIO',5,3,1],

// PINK — 01PM_03PM — Team
['Pink','01PM_03PM','Team','Hygiene & Care','Check for poopy diaper change during nap',2,1,1],
['Pink','01PM_03PM','Team','Hygiene & Care','Check kitchenette: food stored properly, pots washed. cubbinets, dishwasher and plates/utensils are clean',1,2,0],

// PINK — 03PM_06PM — Team
['Pink','03PM_06PM','Team','Hygiene & Care','Tables and under tables are clean after meals.',1,1,1],
['Pink','03PM_06PM','Team','Hygiene & Care','Water bottles are washed and filled.',2,2,1],
['Pink','03PM_06PM','Team','Hygiene & Care','Check if the children are brushed.',1,3,1],
['Pink','03PM_06PM','Team','Hygiene & Care','Check if the children have been changed into clean clothes after nap time if dirty',3,4,1],
['Pink','03PM_06PM','Team','Hygiene & Care','Check if sunscreen has been applied.',2,5,1],
['Pink','03PM_06PM','Team','Hygiene & Care','Check for the presence of sun hats and appropriate clothing (in winter, ensure outerwear is fastened).',1,6,1],
['Pink','03PM_06PM','Team','Hygiene & Care','The children\'s diapers are changed / bags with soiled clothes are labeled.',2,7,1],
['Pink','03PM_06PM','Team','Environment','Closing procedure is done:AC off, trash is out, food stored, laundry is dry, doors locked',1,8,1],
['Pink','03PM_06PM','Team','Safety & Ratio','Compliance with the RATIO.',5,9,1],
['Pink','03PM_06PM','Team','Safety & Ratio','Compliance with ZONING (during outdoor time).',1,10,1],
['Pink','03PM_06PM','Team','Safety & Ratio','Children\'s allergies and dietary preferences are taken into account and followed.',3,11,1],

// PINK — ONCE_DAY / ONCE_MONTH / SEASONAL
['Pink','ONCE_DAY','Team','Classroom organization','Beds, boxes, shelves, hooks, and diapers are labeled.',2,1,1],
['Pink','ONCE_MONTH','Team','Classroom organization','The bulletin board, Boxees,Family Corner, and Birthday Corner are updated',3,1,1],
['Pink','ONCE_MONTH','Team','Classroom organization','Preparation for Special Events: decorations, children\'s songs, art & craft, props & costumes',3,2,1],
['Pink','SEASONAL','Team','Classroom organization','Active participation in decorating rooms and spaces before holidays.',1,1,1],

// PINK — Individual
['Pink','*','Individual','Individual','Timely check‑in & check‑out /Punctuality (Be ready on time)',1,1,1],
['Pink','*','Individual','Individual','Kind and friendly behavior with children, parents and admins',2,2,1],
['Pink','*','Individual','Individual','Addressing accidents/incidents properly (reporting to admins/parents / talk to child / circle time)',4,3,1],
['Pink','*','Individual','Individual','Cleaning, laundry, desinfection',2,4,1],
['Pink','*','Individual','Individual','Code of conduct',1,5,1],
['Pink','*','Individual','Individual','Unauthorized Personal cell phone use during work',5,6,1],
['Pink','*','Individual','Individual','Timely toileting/ diaper change and mandatory water breaks',4,7,1],
['Pink','*','Individual','Individual','Nap time protocol met: 100% supervision, bathroom, kids lying down quietly',2,8,1],
['Pink','*','Individual','Individual','Lesson plans are prepared every Thursday (for the following week).',1,9,1],
['Pink','*','Individual','Individual','Circle time has visual props, posters, books and games, including some physical break every 15 min',3,10,1],
['Pink','*','Individual','Individual','Preparing evaluations. Conducting parent-teacher conference',3,11,1],
['Pink','*','Individual','Individual','Art & craft is colorful, theme related and age appropriate',3,12,1],
['Pink','*','Individual','Individual','Music and movement are theme related and conducted daily 10-20 min. Once a week 30min',2,13,1],
['Pink','*','Individual','Individual','Warning notice(s).',5,14,1],
['Pink','*','Individual','Individual','Preparation for special events (daily rehearsals, props, costumes, decoration, communication with the parents)',3,15,1],
['Pink','*','Individual','Individual','Organizing and supervising special events: shows, field trips, special guests, extravaganzas',3,16,1],
['Pink','*','Individual','Individual','Morning exercise routine is varied every day. P.E. prepared once week.',1,17,1],
['Pink','*','Individual','Individual','New student check list is complete (photos, communication, labels, personal space, etc)',4,18,1],
['Pink','*','Individual','Individual','Bulletin board, boxes, birthday board and family board are up to date',3,19,1],
['Pink','*','Individual','Individual','Parking Zone Check.',1,20,1],

// ═══════════════════════════════════════
// ADMIN — ONCE_DAY — Team
// ═══════════════════════════════════════
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Maintain emotional control and stability in all activities for the wellbeing of children, families, and staff',2,1,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Answer calls, reception, emails, inquiries; schedule tours; send invitations; prepare materials',5,2,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Address parent/staff concerns, resolve issues, inform director, and document if needed',1,3,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Assist in processing and organizing tuition payments, billing, time sheets and invoices while managing enrollment, withdrawals, waitlists, re-enrollment, and children\'s files',2,4,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Handle hiring: ads, screening, interviews, new hire paperwork, training, and staff files',2,5,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Maintain daily calendar, communicate schedule/changes with staff and owner, notify director of appointments and enrollments',2,6,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Conduct short staff meetings and training sessions while providing professional, emotional, and practical support to staff in their daily work, focusing on guidance and assistance rather than discipline',3,7,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Draft/update policies, communicate changes, prepare staff meeting agendas/training materials, update forms',1,8,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Conduct classroom observations (KPI) during time slots (4xDay), provide feedback/discipline/coaching, support staff reviews, prepare classroom reports, and use DOT to monitor teacher responsibilities',4,9,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Prepare rosters, logs, timesheets; maintain staff and children\'s schedules/attendance',2,10,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Communicate positively with parents about accidents and students\'s needs, and organize field trips, events, and conferences.',3,11,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Download/edit/organize children\'s photos and send monthly albums to parents',2,12,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Participate in marketing, recruiting, and event preparation',2,13,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Copy, scan, and procure teacher-requested materials',1,14,1],
['Admin','ONCE_DAY','Team','Professional Knowledge & Execution','Complete additional assignments from supervisor',1,15,0],
['Admin','ONCE_DAY','Team','Safety & Ratio','Verify children\'s attendance daily; confirm absences and notify parents',1,1,1],
['Admin','ONCE_DAY','Team','Safety & Ratio','Ensure staff check-in/out on time; send reminders for signatures',1,2,1],
['Admin','ONCE_DAY','Team','Safety & Ratio','Confirm daily check-outs and record late pick-ups',2,3,1],
['Admin','ONCE_DAY','Team','Safety & Ratio','Provide classroom substitution for breaks or ratio needs; optimize staffing efficiency',2,4,1],
['Admin','ONCE_DAY','Team','Safety & Ratio','Conduct and document fire drills; update emergency info and monitor first aid supplies',1,5,1],
['Admin','ONCE_DAY','Team','Safety & Ratio','Assist with sick/injured students, perform health checks, enforce sick policy, and notify parents',1,6,1],
['Admin','ONCE_DAY','Team','Safety & Ratio','Monitor parking ordinance and ensure children\'s safety during drop-off and pick-up',1,7,1],
['Admin','ONCE_DAY','Team','Safety & Ratio','Manage administrative time effectively to balance duties and priorities',3,8,1],
['Admin','ONCE_DAY','Team','Safety & Ratio','Be at the gate, know each child\'s needs and day, and greet parents warmly',2,9,1],
['Admin','ONCE_DAY','Team','Hygiene & Care','Conduct morning cleanliness and air check (use checklist)',1,1,1],
['Admin','ONCE_DAY','Team','Hygiene & Care','Ensure weekly menu has sufficient food; confirm delivery, freshness, and proper storage',1,2,1],
['Admin','ONCE_DAY','Team','Hygiene & Care','Ensure ordering, receiving, distribution, and inventory of all school supplies',1,3,1],
['Admin','ONCE_DAY','Team','Hygiene & Care','Ensure daily food distribution and enforce hygiene standards',2,4,1],
['Admin','ONCE_DAY','Team','Hygiene & Care','Ensure diaper changes are monitored; children are clean, groomed, and bottles refilled',2,5,1],
['Admin','ONCE_DAY','Team','Hygiene & Care','Ensure kitchen, facilities, and premises are clean and safe',2,6,1],
['Admin','ONCE_DAY','Team','Hygiene & Care','Ensure kitchen tasks: check refrigerator for spoiled food, take out trash, refill water, clean coffee machine, turn off A/C, and lock doors',1,7,1],
['Admin','ONCE_DAY','Team','Classroom organization','Inspect classrooms for cleanliness, bulletin boards, organization, supplies, and labels',1,1,1],
['Admin','ONCE_DAY','Team','Classroom organization','Collect and manage lost & found items',1,2,1],
['Admin','ONCE_DAY','Team','Classroom organization','Support teachers with classroom/program needs and materials',2,3,1],
['Admin','ONCE_DAY','Team','Classroom organization','Maintain and monitor attendance, schedules, and timesheets',1,4,1],
['Admin','ONCE_DAY','Team','Environment','Ensure lobby is clean, organized, cozy, with fresh air and TV on',2,1,1],
['Admin','ONCE_DAY','Team','Environment','Ensure plants are watered and school pets are fed',1,2,1],
['Admin','ONCE_DAY','Team','Environment','Ensure tea and coffee are available for staff',1,3,1],
['Admin','ONCE_DAY','Team','Environment','Ensure photos are updated and displayed',4,4,1],
['Admin','ONCE_DAY','Team','Environment','Ensure communication board and family photo wall are maintained and up to date',2,5,1],
['Admin','ONCE_DAY','Team','Environment','Monitor indoor and outdoor environmental conditions—including cleanliness, air quality, temperature, lighting, and licensing requirements—and ensure appropriate actions',2,6,1],

// ADMIN — ONCE_MONTH — Team
['Admin','ONCE_MONTH','Team','Professional Knowledge & Execution','Create and distribute the school\'s monthly newsletters',2,1,1],
['Admin','ONCE_MONTH','Team','Professional Knowledge & Execution','Monthly Teacher\'s Meeting (agenda)',2,2,1],
['Admin','ONCE_MONTH','Team','Professional Knowledge & Execution','Special Events',3,3,1],
['Admin','ONCE_MONTH','Team','Professional Knowledge & Execution','Transition of the students',3,4,1],
['Admin','ONCE_MONTH','Team','Professional Knowledge & Execution','Charge Late Fees in App',2,5,1],
['Admin','ONCE_MONTH','Team','Safety & Ratio','Monthly Fire/ Earthquake drills',2,1,1],
['Admin','ONCE_MONTH','Team','Classroom organization','Update and maintain the school\'s bulletin board with relevant information',1,1,1],
['Admin','ONCE_MONTH','Team','Classroom organization','Display signs related to school closures, school news, and other important updates',1,2,1],
['Admin','ONCE_MONTH','Team','Classroom organization','Ensure that all required licensing forms and notices are posted as per regulations',4,3,1],
['Admin','ONCE_MONTH','Team','Environment','Photo Albums',3,1,1],

];

foreach ($indicators as $ind) {
    $stmtInd->execute($ind);
}
echo "Indicators: " . count($indicators) . " rows\n";

$db->commit();
auditLog($db, 'seed_config', '', count($indicators) + count($staff) + count($slots) + count($rooms));
echo "\n✅ Seed config done! БД готова до роботи.\n";

} catch (Throwable $e) {
    $db->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
