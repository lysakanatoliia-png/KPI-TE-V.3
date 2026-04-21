#!/bin/bash
# KPI System V3 — щоденний backup MySQL
# Додати до crontab: 0 2 * * * /home/user/public_html/kpi/cron/backup.sh >> /home/user/logs/kpi_backup.log 2>&1

DB_USER="te_kpi_user"
DB_PASS="СЮДИ_ПАРОЛЬ_БД"
DB_NAME="te_kpi"
BACKUP_DIR="/home/user/backups/kpi"
DATE=$(date +%Y-%m-%d)

mkdir -p "$BACKUP_DIR"

mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  --single-transaction \
  --routines \
  --triggers \
  | gzip > "${BACKUP_DIR}/kpi_${DATE}.sql.gz"

if [ $? -eq 0 ]; then
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] OK: kpi_${DATE}.sql.gz"
else
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: mysqldump failed"
  exit 1
fi

# Видалити бекапи старші 30 днів
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +30 -delete
