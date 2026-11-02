-- Couple Connect: Hindi content, endless games, faster "not seen yet" question picks

ALTER TABLE game_sessions MODIFY total_rounds SMALLINT UNSIGNED NOT NULL, MODIFY round_no SMALLINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE game_rounds MODIFY round_no SMALLINT UNSIGNED NOT NULL;
ALTER TABLE game_rounds ADD KEY idx_gr_question (question_id);

ALTER TABLE games ADD COLUMN title_hi VARCHAR(100) NULL AFTER title, ADD COLUMN description_hi VARCHAR(400) NULL AFTER description;
ALTER TABLE question_packs ADD COLUMN title_hi VARCHAR(100) NULL AFTER title;
ALTER TABLE questions ADD COLUMN text_hi VARCHAR(500) NULL AFTER text, ADD COLUMN options_hi JSON NULL AFTER options;
