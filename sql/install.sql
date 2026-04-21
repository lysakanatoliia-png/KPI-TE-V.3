-- KPI System V3 — Tiny Einstein
-- Запустити один раз на хостингу через phpMyAdmin або mysql CLI
-- mysql -u USER -p te_kpi < install.sql

CREATE DATABASE IF NOT EXISTS te_kpi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE te_kpi;

-- ─────────────────────────────────────
-- Конфіг: кімнати
-- ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS rooms (
  room_code  VARCHAR(32)  NOT NULL,
  room_name  VARCHAR(128) NOT NULL,
  is_admin   TINYINT(1)   NOT NULL DEFAULT 0,
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (room_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────
-- Конфіг: тайм-слоти
-- ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS slots (
  slot_code  VARCHAR(32) NOT NULL,
  slot_label VARCHAR(64) NOT NULL,
  slot_short VARCHAR(32) DEFAULT NULL,
  sort_order INT         NOT NULL DEFAULT 0,
  active     TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (slot_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────
-- Конфіг: стаф
-- ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS staff (
  staff_id   VARCHAR(64)  NOT NULL,
  staff_name VARCHAR(128) NOT NULL,
  role       VARCHAR(64)  DEFAULT NULL,
  rooms      VARCHAR(256) DEFAULT NULL,
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────
-- Конфіг: індикатори KPI
-- ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS indicators (
  id             INT           NOT NULL AUTO_INCREMENT,
  room_code      VARCHAR(32)   NOT NULL DEFAULT '*',
  slot_code      VARCHAR(32)   NOT NULL DEFAULT '*',
  scope          ENUM('Team','Individual') NOT NULL DEFAULT 'Team',
  category       VARCHAR(128)  NOT NULL,
  indicator_text VARCHAR(512)  NOT NULL,
  weight         DECIMAL(5,2)  NOT NULL DEFAULT 1.00,
  sort_order     INT           NOT NULL DEFAULT 0,
  active         TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────
-- Присутній стаф у batch (замість CSV)
-- ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS kpi_batch_presence (
  batch_id   VARCHAR(64) NOT NULL,
  staff_id   VARCHAR(64) NOT NULL,
  PRIMARY KEY (batch_id, staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────
-- Головна таблиця: записи KPI
-- ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS kpi_entries (
  id              INT           NOT NULL AUTO_INCREMENT,
  batch_id        VARCHAR(64)   NOT NULL,
  entry_date      DATE          NOT NULL,
  room_code       VARCHAR(32)   NOT NULL,
  slot_code       VARCHAR(32)   NOT NULL,
  staff_id        VARCHAR(64)   NOT NULL,
  scope           ENUM('Team','Individual') NOT NULL,
  indicator_id    INT           NULL,
  indicator_text  VARCHAR(512)  NOT NULL,
  category        VARCHAR(128)  NOT NULL,
  check_value     TINYINT(1)    NOT NULL DEFAULT 1,
  weight          DECIMAL(5,2)  NOT NULL DEFAULT 1.00,
  earned_points   DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
  possible_points DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
  comment         TEXT          NULL,
  submitted_by    VARCHAR(64)   NULL,
  created_at      DATETIME      NOT NULL DEFAULT NOW(),
  PRIMARY KEY (id),
  -- Дедублікація: один batch+staff+indicator не може бути двічі
  UNIQUE KEY  uq_entry      (batch_id, staff_id, indicator_id),
  KEY         idx_date      (entry_date),
  KEY         idx_room_date (room_code, entry_date),
  KEY         idx_staff_dt  (staff_id, entry_date),
  KEY         idx_batch     (batch_id),
  KEY         idx_violation (check_value, entry_date),
  KEY         idx_scope_dt  (scope, entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────
-- Лог всіх дій (аудит)
-- ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_log (
  id         INT          NOT NULL AUTO_INCREMENT,
  created_at DATETIME     NOT NULL DEFAULT NOW(),
  user       VARCHAR(64)  NULL,
  action     VARCHAR(64)  NOT NULL,
  batch_id   VARCHAR(64)  NULL,
  ip         VARCHAR(45)  NULL,
  rows_saved INT          NULL,
  note       TEXT         NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
