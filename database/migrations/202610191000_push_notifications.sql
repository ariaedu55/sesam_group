-- Notifications : dans l'application (cloche) et sur les appareils (push Firebase Cloud Messaging),
-- pour les commerces et pour la console SESAM. Préférences par catégorie et anti-doublon des rappels.

-- Le personnel SESAM n'a pas de commerce : ses notifications ont tenant_id NULL.
ALTER TABLE notifications
    DROP FOREIGN KEY fk_notifications_tenant;
ALTER TABLE notifications
    MODIFY tenant_id INT UNSIGNED NULL,
    ADD COLUMN category VARCHAR(40) NOT NULL DEFAULT 'general' AFTER user_id,
    ADD COLUMN level VARCHAR(10) NOT NULL DEFAULT 'info' AFTER category,
    ADD COLUMN link VARCHAR(255) NULL AFTER body,
    ADD COLUMN push_status VARCHAR(20) NULL AFTER link,
    ADD KEY idx_notifications_user_read (user_id, read_at, created_at);
ALTER TABLE notifications
    ADD CONSTRAINT fk_notifications_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE;

-- Appareils (navigateur, téléphone) qui reçoivent les notifications push d'un compte.
CREATE TABLE IF NOT EXISTS push_devices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NULL,
    token VARCHAR(512) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    label VARCHAR(120) NULL,
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    last_sent_at DATETIME NULL,
    last_error VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_push_devices_token (token_hash),
    KEY idx_push_devices_user (user_id),
    CONSTRAINT fk_push_devices_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_push_devices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catégories coupées par un utilisateur (par défaut, tout est activé).
CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id INT UNSIGNED NOT NULL,
    category VARCHAR(40) NOT NULL,
    in_app TINYINT(1) NOT NULL DEFAULT 1,
    push TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, category),
    CONSTRAINT fk_notification_preferences_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un rappel planifié (ex. « échéance dans 3 jours » pour tel loyer) n'est envoyé qu'une fois.
CREATE TABLE IF NOT EXISTS notification_keys (
    key_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (key_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une notification disparaît avec le compte qui la reçoit.
ALTER TABLE notifications DROP FOREIGN KEY fk_notifications_user;
ALTER TABLE notifications ADD CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE;
