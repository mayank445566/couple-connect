-- Phase 5: admin panel. Admin accounts are separate from app users and have their own sessions.

CREATE TABLE admin_users (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email            VARCHAR(190) NOT NULL,
  name             VARCHAR(60)  NOT NULL,
  password_hash    VARCHAR(255) NOT NULL,
  -- owner: everything. moderator: users + reports. finance: orders, refunds, grants. content: games, questions, themes.
  role             ENUM('owner','moderator','finance','content') NOT NULL,
  status           ENUM('active','disabled') NOT NULL DEFAULT 'active',
  totp_secret      VARCHAR(255) NULL,          -- encrypted with APP_KEY
  totp_enabled_at  DATETIME NULL,
  totp_last_step   BIGINT NULL,                -- blocks replay of the same 30 s code
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  failed_logins    INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until     DATETIME NULL,
  last_login_at    DATETIME NULL,
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_sessions (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id      INT UNSIGNED NOT NULL,
  token_hash    CHAR(64) NOT NULL,
  csrf_token    CHAR(64) NOT NULL,
  stage         ENUM('2fa','active') NOT NULL DEFAULT 'active',
  ip            VARCHAR(45) NOT NULL,
  user_agent    VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at    DATETIME NOT NULL,
  revoked_at    DATETIME NULL,
  twofa_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  flash         VARCHAR(500) NULL,
  UNIQUE KEY uq_admin_sess (token_hash),
  KEY idx_admin_sess_admin (admin_id),
  CONSTRAINT fk_admin_sess FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only record of every admin action. The panel has no delete for it.
CREATE TABLE admin_audit_log (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id     INT UNSIGNED NULL,
  admin_email  VARCHAR(190) NOT NULL,
  action       VARCHAR(60) NOT NULL,
  target_type  VARCHAR(30) NULL,
  target_id    BIGINT UNSIGNED NULL,
  details      JSON NULL,
  ip           VARCHAR(45) NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_time (created_at),
  KEY idx_audit_target (target_type, target_id),
  KEY idx_audit_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE reports
  ADD COLUMN handled_by INT UNSIGNED NULL,
  ADD COLUMN handled_at DATETIME NULL,
  ADD COLUMN action_taken ENUM('none','suspended','banned') NULL,
  ADD COLUMN resolution_note VARCHAR(1000) NULL;

ALTER TABLE users
  ADD COLUMN status_reason VARCHAR(255) NULL;

ALTER TABLE orders
  ADD COLUMN refunded_at DATETIME NULL,
  ADD COLUMN refund_gateway_id VARCHAR(100) NULL,
  ADD COLUMN refunded_by_admin INT UNSIGNED NULL;

-- Audited case access: an owner can open ONE couple's chat and media for a stated case
-- (user report, suspected illegal activity, legal request, safety emergency). Fresh 2FA code required, 24 h expiry,
-- every view written to admin_audit_log. Disclosed to users in the privacy policy.
CREATE TABLE case_access_grants (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id      INT UNSIGNED NOT NULL,
  couple_id     BIGINT UNSIGNED NOT NULL,
  basis         ENUM('user_report','suspected_illegal','legal_request','safety_emergency') NOT NULL,
  reference     VARCHAR(100) NOT NULL,
  reason        VARCHAR(1000) NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at    DATETIME NOT NULL,
  closed_at     DATETIME NULL,
  KEY idx_case_admin (admin_id, expires_at),
  KEY idx_case_couple (couple_id),
  CONSTRAINT fk_case_admin FOREIGN KEY (admin_id) REFERENCES admin_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
