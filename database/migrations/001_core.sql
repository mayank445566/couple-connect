-- Couple Connect: core schema (identity, pairing, chat, sync)
-- MySQL 8.0+ / MariaDB 10.6+, InnoDB, utf8mb4. All timestamps are UTC.

CREATE TABLE users (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email                 VARCHAR(190) NULL,
  phone                 VARCHAR(20)  NULL,
  password_hash         VARCHAR(255) NOT NULL,
  display_name          VARCHAR(60)  NOT NULL,
  dob                   DATE         NOT NULL,
  relationship_status   ENUM('dating','engaged','married','long_distance','other') NOT NULL DEFAULT 'dating',
  avatar_media_id       BIGINT UNSIGNED NULL,
  bio                   VARCHAR(160) NULL,
  email_verified_at     DATETIME NULL,
  phone_verified_at     DATETIME NULL,
  status                ENUM('active','suspended','banned','deleted') NOT NULL DEFAULT 'active',
  active_couple_id      BIGINT UNSIGNED NULL,
  last_seen_visibility  ENUM('partner','nobody') NOT NULL DEFAULT 'partner',
  read_receipts         TINYINT(1) NOT NULL DEFAULT 1,
  adult_opt_in_at       DATETIME NULL,
  deletion_requested_at DATETIME NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_phone (phone),
  KEY idx_users_couple (active_couple_id),
  KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per logged-in device. Only the SHA-256 of the bearer token is stored.
CREATE TABLE auth_tokens (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  token_hash   CHAR(64) NOT NULL,
  device_name  VARCHAR(100) NULL,
  platform     VARCHAR(20)  NULL,
  ip           VARCHAR(45)  NULL,
  user_agent   VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at   DATETIME NOT NULL,
  revoked_at   DATETIME NULL,
  UNIQUE KEY uq_auth_token (token_hash),
  KEY idx_auth_user (user_id),
  CONSTRAINT fk_auth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-time codes (email verification, password reset). Code stored as HMAC.
CREATE TABLE user_codes (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL,
  purpose     ENUM('verify_email','reset_password','verify_phone') NOT NULL,
  code_hash   CHAR(64) NOT NULL,
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_codes_user (user_id, purpose),
  CONSTRAINT fk_codes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  k         VARCHAR(191) NOT NULL PRIMARY KEY,
  hits      INT UNSIGNED NOT NULL,
  reset_at  DATETIME NOT NULL,
  KEY idx_rl_reset (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE couples (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_code           VARCHAR(12) NOT NULL,
  user_a_id             BIGINT UNSIGNED NOT NULL,
  user_b_id             BIGINT UNSIGNED NOT NULL,
  status                ENUM('active','ended') NOT NULL DEFAULT 'active',
  relationship_start    DATE NULL,
  anniversary_date      DATE NULL,
  next_meeting_at       DATETIME NULL,
  disappearing_seconds  INT UNSIGNED NOT NULL DEFAULT 0,
  wallpaper             VARCHAR(100) NULL,
  paired_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at              DATETIME NULL,
  ended_by              BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_couple_code (public_code),
  KEY idx_couple_a (user_a_id),
  KEY idx_couple_b (user_b_id),
  CONSTRAINT fk_couple_a FOREIGN KEY (user_a_id) REFERENCES users(id),
  CONSTRAINT fk_couple_b FOREIGN KEY (user_b_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invite lifecycle: open -> pending_approval (invitee joined) -> accepted | rejected
--                   open|pending_approval -> cancelled | expired
CREATE TABLE pair_requests (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inviter_id    BIGINT UNSIGNED NOT NULL,
  invitee_id    BIGINT UNSIGNED NULL,
  code_hash     CHAR(64) NOT NULL,
  qr_token_hash CHAR(64) NOT NULL,
  status        ENUM('open','pending_approval','accepted','rejected','cancelled','expired') NOT NULL DEFAULT 'open',
  expires_at    DATETIME NOT NULL,
  joined_at     DATETIME NULL,
  responded_at  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pr_code (code_hash, status),
  KEY idx_pr_qr (qr_token_hash, status),
  KEY idx_pr_inviter (inviter_id, status),
  KEY idx_pr_invitee (invitee_id, status),
  CONSTRAINT fk_pr_inviter FOREIGN KEY (inviter_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_pr_invitee FOREIGN KEY (invitee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_blocks (
  blocker_id  BIGINT UNSIGNED NOT NULL,
  blocked_id  BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (blocker_id, blocked_id),
  CONSTRAINT fk_blk_a FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_blk_b FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Encrypted-at-rest uploads. storage_name is random; files live outside the web root.
CREATE TABLE media (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id      BIGINT UNSIGNED NOT NULL,
  couple_id     BIGINT UNSIGNED NULL,
  kind          ENUM('image','video','audio','voice','file','avatar') NOT NULL,
  mime          VARCHAR(100) NOT NULL,
  original_name VARCHAR(190) NULL,
  size_bytes    BIGINT UNSIGNED NOT NULL,
  width         INT UNSIGNED NULL,
  height        INT UNSIGNED NULL,
  duration_ms   INT UNSIGNED NULL,
  storage_name  CHAR(48) NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at    DATETIME NULL,
  UNIQUE KEY uq_media_storage (storage_name),
  KEY idx_media_owner (owner_id),
  KEY idx_media_couple (couple_id),
  CONSTRAINT fk_media_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  couple_id        BIGINT UNSIGNED NOT NULL,
  sender_id        BIGINT UNSIGNED NOT NULL,
  client_id        CHAR(36) NOT NULL,
  type             ENUM('text','image','video','audio','voice','file','sticker','gif','system') NOT NULL DEFAULT 'text',
  body             TEXT NULL,
  media_id         BIGINT UNSIGNED NULL,
  meta             JSON NULL,
  reply_to_id      BIGINT UNSIGNED NULL,
  forwarded        TINYINT(1) NOT NULL DEFAULT 0,
  created_at       DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  edited_at        DATETIME NULL,
  deleted_at       DATETIME NULL,
  delivered_at     DATETIME NULL,
  read_at          DATETIME NULL,
  pinned_at        DATETIME NULL,
  pinned_by        BIGINT UNSIGNED NULL,
  expires_at       DATETIME NULL,
  UNIQUE KEY uq_msg_client (sender_id, client_id),
  KEY idx_msg_couple (couple_id, id),
  KEY idx_msg_unread (couple_id, sender_id, read_at),
  KEY idx_msg_pinned (couple_id, pinned_at),
  KEY idx_msg_expires (expires_at),
  FULLTEXT KEY ft_msg_body (body),
  CONSTRAINT fk_msg_couple FOREIGN KEY (couple_id) REFERENCES couples(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message_reactions (
  message_id  BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  emoji       VARCHAR(16) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id, user_id),
  CONSTRAINT fk_react_msg FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
  CONSTRAINT fk_react_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message_stars (
  user_id     BIGINT UNSIGNED NOT NULL,
  message_id  BIGINT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, message_id),
  CONSTRAINT fk_star_msg FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
  CONSTRAINT fk_star_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Delete for me"
CREATE TABLE message_hidden (
  user_id     BIGINT UNSIGNED NOT NULL,
  message_id  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, message_id),
  CONSTRAINT fk_hid_msg FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
  CONSTRAINT fk_hid_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only change log per couple; clients sync with GET /sync?since=<id>
CREATE TABLE couple_events (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  couple_id   BIGINT UNSIGNED NOT NULL,
  actor_id    BIGINT UNSIGNED NULL,
  type        VARCHAR(40) NOT NULL,
  payload     JSON NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ev_couple (couple_id, id),
  KEY idx_ev_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE presence (
  user_id       BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  last_seen_at  DATETIME NOT NULL,
  typing_until  DATETIME NULL,
  CONSTRAINT fk_pres_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reporter_id  BIGINT UNSIGNED NOT NULL,
  reported_id  BIGINT UNSIGNED NULL,
  couple_id    BIGINT UNSIGNED NULL,
  reason       ENUM('harassment','abuse','underage','impersonation','spam','other') NOT NULL,
  details      TEXT NULL,
  attached     JSON NULL,
  status       ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_reports_status (status),
  CONSTRAINT fk_rep_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schema_migrations (
  version     VARCHAR(100) NOT NULL PRIMARY KEY,
  applied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
