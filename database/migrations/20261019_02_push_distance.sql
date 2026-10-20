-- Raio de notificações: um ponto de referência por profissional.
-- Não altera a localização residencial/cadastral do perfil.
CREATE TABLE IF NOT EXISTS tp_push_preferences (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    radius_km SMALLINT UNSIGNED NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_push_preference_user FOREIGN KEY (user_id) REFERENCES tp_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
