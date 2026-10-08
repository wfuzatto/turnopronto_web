-- TurnoPronto Web - acompanhamento de vagas
-- Execute em instalações existentes que preferirem aplicar a estrutura manualmente.
-- A aplicação também garante esta tabela automaticamente ao usar o recurso.

CREATE TABLE IF NOT EXISTS tp_shift_followers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shift_id BIGINT UNSIGNED NOT NULL,
  professional_id BIGINT UNSIGNED NOT NULL,
  followed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_shift_follower (shift_id,professional_id),
  CONSTRAINT fk_shift_follower_shift FOREIGN KEY (shift_id) REFERENCES tp_shifts(id) ON DELETE CASCADE,
  CONSTRAINT fk_shift_follower_prof FOREIGN KEY (professional_id) REFERENCES tp_professionals(id) ON DELETE CASCADE,
  INDEX idx_shift_followers_prof (professional_id,followed_at),
  INDEX idx_shift_followers_shift (shift_id,followed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
