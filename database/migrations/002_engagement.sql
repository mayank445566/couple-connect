-- Couple Connect Phase 2: games, commerce, themes, long-distance hub, phone OTP

-- ---------- Games ----------
-- mode: prompt (answer/do it), match (both pick, compare), guess (one answers about self, other guesses), quiz (has a correct answer)
CREATE TABLE games (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(60) NOT NULL,
  title        VARCHAR(100) NOT NULL,
  description  VARCHAR(400) NOT NULL,
  section      ENUM('free','adult') NOT NULL DEFAULT 'free',
  mode         ENUM('prompt','match','guess','quiz') NOT NULL,
  is_premium   TINYINT(1) NOT NULL DEFAULT 0,
  rounds       TINYINT UNSIGNED NOT NULL DEFAULT 10,
  icon         VARCHAR(40) NULL,
  config       JSON NULL,
  enabled      TINYINT(1) NOT NULL DEFAULT 1,
  sort_order   INT NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_games_slug (slug),
  KEY idx_games_section (section, enabled, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- level: easy|medium|hard for free games; flirty|romantic|bold|intimate|spicy for adult games
CREATE TABLE question_packs (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  game_id      INT UNSIGNED NOT NULL,
  couple_id    BIGINT UNSIGNED NULL,           -- set for a couple's own custom pack
  slug         VARCHAR(80) NULL,
  title        VARCHAR(100) NOT NULL,
  level        VARCHAR(20) NOT NULL DEFAULT 'easy',
  is_premium   TINYINT(1) NOT NULL DEFAULT 0,
  enabled      TINYINT(1) NOT NULL DEFAULT 1,
  sort_order   INT NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pack_slug (slug),
  KEY idx_pack_game (game_id, enabled),
  KEY idx_pack_couple (couple_id),
  CONSTRAINT fk_pack_game FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- kind: question|truth|dare|challenge|statement|two_truths
-- options: ["A","B"] | ["@couple"] (pick one of the two partners) | [{"t":"...","cat":"words"}]
CREATE TABLE questions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pack_id     INT UNSIGNED NOT NULL,
  kind        VARCHAR(20) NOT NULL DEFAULT 'question',
  text        VARCHAR(500) NOT NULL,
  options     JSON NULL,
  correct     VARCHAR(200) NULL,
  enabled     TINYINT(1) NOT NULL DEFAULT 1,
  created_by  BIGINT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_q_pack (pack_id, enabled, kind),
  CONSTRAINT fk_q_pack FOREIGN KEY (pack_id) REFERENCES question_packs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE game_sessions (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  couple_id     BIGINT UNSIGNED NOT NULL,
  game_id       INT UNSIGNED NOT NULL,
  pack_id       INT UNSIGNED NULL,
  started_by    BIGINT UNSIGNED NOT NULL,
  status        ENUM('invited','active','finished','stopped','declined') NOT NULL DEFAULT 'invited',
  level         VARCHAR(20) NULL,
  total_rounds  TINYINT UNSIGNED NOT NULL,
  round_no      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  scores        JSON NULL,
  stopped_by    BIGINT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  finished_at   DATETIME NULL,
  KEY idx_gs_couple (couple_id, status, id),
  CONSTRAINT fk_gs_couple FOREIGN KEY (couple_id) REFERENCES couples(id) ON DELETE CASCADE,
  CONSTRAINT fk_gs_game FOREIGN KEY (game_id) REFERENCES games(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE game_rounds (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id   BIGINT UNSIGNED NOT NULL,
  round_no     TINYINT UNSIGNED NOT NULL,
  question_id  INT UNSIGNED NOT NULL,
  subject_id   BIGINT UNSIGNED NULL,  -- guess/prompt modes: the partner the question is about / who answers
  status       ENUM('open','revealed','skipped') NOT NULL DEFAULT 'open',
  result       JSON NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revealed_at  DATETIME NULL,
  UNIQUE KEY uq_round (session_id, round_no),
  CONSTRAINT fk_gr_session FOREIGN KEY (session_id) REFERENCES game_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE game_answers (
  round_id    BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  answer      JSON NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (round_id, user_id),
  CONSTRAINT fk_ga_round FOREIGN KEY (round_id) REFERENCES game_rounds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily question for both partners (one row per couple per day per user)
CREATE TABLE daily_answers (
  couple_id   BIGINT UNSIGNED NOT NULL,
  day         DATE NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  answer      VARCHAR(1000) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (couple_id, day, user_id),
  CONSTRAINT fk_da_couple FOREIGN KEY (couple_id) REFERENCES couples(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Commerce ----------
CREATE TABLE settings (
  k           VARCHAR(80) NOT NULL PRIMARY KEY,
  v           TEXT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku           VARCHAR(60) NOT NULL,
  title         VARCHAR(100) NOT NULL,
  description   VARCHAR(400) NULL,
  price_paise   INT UNSIGNED NOT NULL,
  currency      CHAR(3) NOT NULL DEFAULT 'INR',
  grants        JSON NOT NULL,              -- e.g. ["premium","themes"]
  active        TINYINT(1) NOT NULL DEFAULT 1,
  play_product_id VARCHAR(100) NULL,        -- Google Play in-app product id (Phase 6)
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_products_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id           BIGINT UNSIGNED NOT NULL,
  product_id        INT UNSIGNED NOT NULL,
  gateway           VARCHAR(20) NOT NULL,
  gateway_order_id  VARCHAR(100) NOT NULL,
  amount_paise      INT UNSIGNED NOT NULL,
  currency          CHAR(3) NOT NULL,
  status            ENUM('created','paid','failed','refunded') NOT NULL DEFAULT 'created',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at           DATETIME NULL,
  UNIQUE KEY uq_orders_gateway (gateway, gateway_order_id),
  KEY idx_orders_user (user_id, status),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_orders_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id            BIGINT UNSIGNED NOT NULL,
  gateway             VARCHAR(20) NOT NULL,
  gateway_payment_id  VARCHAR(100) NOT NULL,
  amount_paise        INT UNSIGNED NOT NULL,
  status              VARCHAR(20) NOT NULL,
  source              ENUM('client_verify','webhook','admin') NOT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payments_gateway (gateway, gateway_payment_id),
  KEY idx_payments_order (order_id),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entitlements (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      BIGINT UNSIGNED NOT NULL,
  entitlement  VARCHAR(40) NOT NULL,
  order_id     BIGINT UNSIGNED NULL,
  granted_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at   DATETIME NULL,
  UNIQUE KEY uq_ent (user_id, entitlement),
  CONSTRAINT fk_ent_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_events (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gateway       VARCHAR(20) NOT NULL,
  event_id      VARCHAR(100) NOT NULL,
  type          VARCHAR(60) NOT NULL,
  payload       JSON NOT NULL,
  processed_at  DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_webhook (gateway, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Themes ----------
CREATE TABLE themes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(40) NOT NULL,
  name        VARCHAR(60) NOT NULL,
  is_premium  TINYINT(1) NOT NULL DEFAULT 0,
  tokens      JSON NOT NULL,       -- colors, gradients, bubble colors, dark flag
  wallpaper_media_id BIGINT UNSIGNED NULL,
  enabled     TINYINT(1) NOT NULL DEFAULT 1,
  sort_order  INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_theme_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_preferences (
  user_id        BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  theme_slug     VARCHAR(40) NOT NULL DEFAULT 'default',
  color_mode     ENUM('system','light','dark') NOT NULL DEFAULT 'system',
  accent         CHAR(7) NULL,
  bubble_mine    CHAR(7) NULL,
  bubble_theirs  CHAR(7) NULL,
  font_scale     DECIMAL(3,2) NOT NULL DEFAULT 1.00,
  wallpaper      VARCHAR(100) NULL,
  notify_messages TINYINT(1) NOT NULL DEFAULT 1,
  notify_games    TINYINT(1) NOT NULL DEFAULT 1,
  notify_reminders TINYINT(1) NOT NULL DEFAULT 1,
  show_preview    TINYINT(1) NOT NULL DEFAULT 0,
  quiet_start     TIME NULL,
  quiet_end       TIME NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pref_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Long-distance hub & shared space ----------
CREATE TABLE love_taps (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  couple_id   BIGINT UNSIGNED NOT NULL,
  sender_id   BIGINT UNSIGNED NOT NULL,
  kind        ENUM('hug','kiss','heartbeat','miss_you','thinking_of_you','good_morning','good_night') NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_taps (couple_id, created_at),
  CONSTRAINT fk_taps_couple FOREIGN KEY (couple_id) REFERENCES couples(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE moods (
  couple_id   BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  day         DATE NOT NULL,
  mood        ENUM('happy','loved','excited','calm','tired','stressed','sad','missing_you','angry') NOT NULL,
  note        VARCHAR(200) NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (couple_id, user_id, day),
  CONSTRAINT fk_moods_couple FOREIGN KEY (couple_id) REFERENCES couples(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE countdowns (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  couple_id   BIGINT UNSIGNED NOT NULL,
  created_by  BIGINT UNSIGNED NOT NULL,
  title       VARCHAR(100) NOT NULL,
  target_at   DATETIME NOT NULL,
  emoji       VARCHAR(16) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cd (couple_id, target_at),
  CONSTRAINT fk_cd_couple FOREIGN KEY (couple_id) REFERENCES couples(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bucket_items (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  couple_id   BIGINT UNSIGNED NOT NULL,
  created_by  BIGINT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  notes       VARCHAR(1000) NULL,
  category    VARCHAR(30) NULL,
  done_at     DATETIME NULL,
  done_by     BIGINT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_bucket (couple_id, done_at),
  CONSTRAINT fk_bucket_couple FOREIGN KEY (couple_id) REFERENCES couples(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE calendar_events (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  couple_id       BIGINT UNSIGNED NOT NULL,
  created_by      BIGINT UNSIGNED NOT NULL,
  title           VARCHAR(150) NOT NULL,
  kind            ENUM('date','birthday','anniversary','reminder','trip','call','other') NOT NULL DEFAULT 'other',
  starts_at       DATETIME NOT NULL,
  ends_at         DATETIME NULL,
  all_day         TINYINT(1) NOT NULL DEFAULT 0,
  repeat_yearly   TINYINT(1) NOT NULL DEFAULT 0,
  remind_minutes  INT UNSIGNED NULL,
  notes           VARCHAR(1000) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cal (couple_id, starts_at),
  CONSTRAINT fk_cal_couple FOREIGN KEY (couple_id) REFERENCES couples(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal entries, love letters (optionally scheduled = surprise), shared notes, private wishes
CREATE TABLE notes (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  couple_id   BIGINT UNSIGNED NOT NULL,
  author_id   BIGINT UNSIGNED NOT NULL,
  kind        ENUM('journal','letter','note','wish') NOT NULL,
  title       VARCHAR(150) NULL,
  body        TEXT NOT NULL,
  deliver_at  DATETIME NULL,
  opened_at   DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_notes (couple_id, kind, id),
  CONSTRAINT fk_notes_couple FOREIGN KEY (couple_id) REFERENCES couples(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE memories (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  couple_id    BIGINT UNSIGNED NOT NULL,
  created_by   BIGINT UNSIGNED NOT NULL,
  title        VARCHAR(150) NOT NULL,
  body         VARCHAR(2000) NULL,
  media_id     BIGINT UNSIGNED NULL,
  happened_on  DATE NOT NULL,
  is_milestone TINYINT(1) NOT NULL DEFAULT 0,
  is_favorite  TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mem (couple_id, happened_on),
  CONSTRAINT fk_mem_couple FOREIGN KEY (couple_id) REFERENCES couples(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Phone OTP ----------
CREATE TABLE phone_otps (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  phone       VARCHAR(20) NOT NULL,
  code_hash   CHAR(64) NOT NULL,
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_otp_phone (phone, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN adult_age_confirmed_at DATETIME NULL AFTER adult_opt_in_at;
ALTER TABLE notes ADD COLUMN announced_at DATETIME NULL AFTER opened_at;
