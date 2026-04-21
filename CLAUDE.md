# KPI V3 — Claude Instructions

## Що це за проект
KPI-система V3 для дитячого садочку **Tiny Einstein** (dev.tinyeinstein.org/kpi/).
Переписана з нуля: MySQL замість Google Sheets, без GAS, без синку.

## Робоча папка
`~/claude-workspace/documents/KPI -TE/KPI-V.3/`

---

## Архітектура (одна лінія)
```
form.html → api/kpi.php (saveBatch) → MySQL (te_kpi)
dashboard.html → api/reports.php → MySQL
```
Ніяких черг, ніяких JSON-файлів, ніякого GAS.

---

## Математика KPI

```
EarnedPoints   = Weight (якщо check=yes) або 0 (якщо check=no)
PossiblePoints = Weight (завжди)

TeamKPI%       = ΣTeamEarned   / ΣTeamPossible   × 100
IndividualKPI% = ΣIndEarned    / ΣIndPossible     × 100

TotalKPI%      = TeamKPI% × 0.8 + IndividualKPI% × 0.2
               АБО TeamKPI% × 1.0 (якщо IndPossible=0 АБО кімната Admin)
```

---

## Файлова структура

| Файл | Роль | Фаза |
|------|------|------|
| `sql/install.sql` | Всі 6 таблиць БД | 1 |
| `sql/seed_data.sql` | Початкові rooms + slots | 1 |
| `api/config.env.php` | DB credentials (не в git!) | 1 |
| `api/db.php` | PDO singleton, jsonOut(), auditLog(), helpers | 1 |
| `api/config.php` | GET → rooms, slots, staff, indicators | 1 |
| `api/kpi.php` | POST saveBatch, GET checkDuplicate | 2 |
| `api/reports.php` | GET summary / violations / daily / monthly | 3 |
| `assets/app.js` | escapeHtml(), apiFetch(), showBanner(), kpiClass() | 4 |
| `assets/style.css` | Спільні стилі форми та дашборду | 4 |
| `assets/form.js` | Логіка форми введення KPI | 4 |
| `form.html` | Форма введення KPI | 4 |
| `assets/dashboard.js` | Логіка дашборду | 5 |
| `dashboard.html` | Дашборд перегляду | 5 |
| `migrate/seed_config.php` | Заповнює rooms/slots/staff/indicators | 6 |
| `migrate/migrate_v2.php` | Імпорт kpi_history.json → MySQL | 6 |
| `migrate/migrate_excel.php` | Імпорт KPI.xlsx → MySQL (CSV або PhpSpreadsheet) | 6 |
| `.htaccess` | Basic Auth + CORS + захист конфіга | 7 |
| `cron/backup.sh` | Щоденний mysqldump + 30-денна ротація | 7 |
| `index.html` | Redirect → form.html | — |

---

## Стан розробки: ВСІ ФАЙЛИ СТВОРЕНО ✅
Всі 7 фаз завершені (20.04.2026). Готово до деплою на хостинг.

---

## Деплой — покроково

1. Завантажити вміст `KPI-V.3/` на сервер → `/public_html/kpi/`
2. Створити БД `te_kpi` у phpMyAdmin
3. Запустити `sql/install.sql` (6 таблиць)
4. Заповнити `api/config.env.php` реальними даними хостингу
5. Перевірити: `GET /kpi/api/config.php` → `{"ok":true,...}`
6. Заповнити масиви `$staff` і `$indicators` у `migrate/seed_config.php`
7. Запустити `migrate/seed_config.php` через браузер або CLI
8. Запустити `migrate/migrate_v2.php` (імпорт з kpi_history.json V2)
9. Запустити `migrate/migrate_excel.php --csv kpi.csv` (імпорт Excel)
10. Перевірити дашборд: місячні суми мають збігатись з Excel
11. Налаштувати `.htpasswd`: `htpasswd -c /home/user/.htpasswd manager`
12. Оновити шлях `AuthUserFile` у `.htaccess`
13. Додати backup.sh до crontab: `0 2 * * * /path/to/backup.sh`
14. Заблокувати `migrate/` у `.htaccess` після завершення міграції

---

## База даних

**БД:** `te_kpi`
**Таблиці:** rooms, slots, staff, indicators, kpi_entries, kpi_batch_presence, audit_log

### Ключова: kpi_entries
Містить **snapshot** полів weight/indicator_text/category на момент введення.
Розрахунки KPI **завжди** з цих полів, НЕ з таблиці indicators.
Якщо вага зміниться — старі записи залишаються незмінними (аудиторський слід).

---

## Важливі технічні деталі

- **LIMIT в PDO:** обов'язково `bindValue(':lim', $n, PDO::PARAM_INT)` при `EMULATE_PREPARES=false`
- **XSS:** у JS завжди `escapeHtml()` або `textContent`, ніколи `innerHTML` з даними БД
- **CORS:** `Access-Control-Allow-Origin: https://dev.tinyeinstein.org` (не `*`)
- **Timezone:** `America/Los_Angeles` (у config.env.php)
- **Дедублікація:** `UNIQUE KEY (batch_id, staff_id, indicator_id)` + `INSERT IGNORE`
- **Транзакція:** весь saveBatch в одній транзакції (beginTransaction / commit / rollBack)
- **Auth:** PHP_AUTH_USER → getAuthUser() → записується в submitted_by і audit_log

---

## Документи

- `PRD.html` — Product Requirements Document (що і навіщо)
- `TECH_SPEC.html` — Technical Specification v2 (як саме, покроково, 14 розділів)

---

## Resumption Guide

При поверненні в нову сесію:
1. Прочитай `TECH_SPEC.html` Розділ 14
2. Визнач яка фаза завершена (перевір через Розділ 14 таблицю)
3. Продовжуй з наступної фази

**Повідомлення для нової сесії:**
> "Ми розробляємо KPI V3. Всі файли створені, документи в `~/claude-workspace/documents/KPI -TE/KPI-V.3/`. Прочитай CLAUDE.md і TECH_SPEC.html розділ 14."
