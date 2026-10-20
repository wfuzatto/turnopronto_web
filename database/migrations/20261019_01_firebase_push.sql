-- A migração pode ser executada no phpMyAdmin antes de habilitar o cron.
-- Alternativamente, Push::ensureSchema() cria as tabelas sob demanda.
CREATE TABLE IF NOT EXISTS tp_push_devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  token TEXT NOT NULL,
  platform VARCHAR(12) NOT NULL DEFAULT 'android',
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_push_token (token_hash),
  INDEX idx_push_user (user_id,enabled),
  CONSTRAINT fk_push_device_user FOREIGN KEY (user_id) REFERENCES tp_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tp_push_deliveries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_id BIGINT UNSIGNED NOT NULL,
  device_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  locked_at DATETIME NULL,
  sent_at DATETIME NULL,
  last_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_push_delivery (notification_id,device_id),
  INDEX idx_push_pending (status,available_at),
  CONSTRAINT fk_push_delivery_notification FOREIGN KEY (notification_id) REFERENCES tp_notifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_push_delivery_device FOREIGN KEY (device_id) REFERENCES tp_push_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
