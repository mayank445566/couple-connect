-- Couple Connect Phase 4: calls, WebRTC signalling, push notification tokens

CREATE TABLE calls (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  couple_id    BIGINT UNSIGNED NOT NULL,
  caller_id    BIGINT UNSIGNED NOT NULL,
  callee_id    BIGINT UNSIGNED NOT NULL,
  kind         ENUM('audio','video') NOT NULL DEFAULT 'audio',
  status       ENUM('ringing','active','ended','declined','missed','cancelled') NOT NULL DEFAULT 'ringing',
  end_reason   VARCHAR(30) NULL,
  ended_by     BIGINT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  answered_at  DATETIME NULL,
  ended_at     DATETIME NULL,
  KEY idx_calls_couple (couple_id, id),
  KEY idx_calls_status (status, created_at),
  CONSTRAINT fk_calls_couple FOREIGN KEY (couple_id) REFERENCES couples(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Offers, answers and ICE candidates. Short-lived: cron deletes them after the call ends.
CREATE TABLE call_signals (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  call_id      BIGINT UNSIGNED NOT NULL,
  from_user_id BIGINT UNSIGNED NOT NULL,
  kind         ENUM('offer','answer','ice','renegotiate') NOT NULL,
  payload      MEDIUMTEXT NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sig_call (call_id, id),
  CONSTRAINT fk_sig_call FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per signed-in device that accepts push. Tied to the session token, so signing out stops pushes.
CREATE TABLE push_tokens (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       BIGINT UNSIGNED NOT NULL,
  auth_token_id BIGINT UNSIGNED NOT NULL,
  provider      ENUM('fcm') NOT NULL DEFAULT 'fcm',
  token         VARCHAR(512) NOT NULL,
  platform      VARCHAR(20) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_error    VARCHAR(100) NULL,
  UNIQUE KEY uq_push_token (token(191)),
  KEY idx_push_user (user_id),
  CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_push_auth FOREIGN KEY (auth_token_id) REFERENCES auth_tokens(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
