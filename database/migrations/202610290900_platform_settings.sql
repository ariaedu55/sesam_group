-- Réglages de la plateforme SESAM modifiables depuis la console (ex. essai gratuit à l'inscription).
CREATE TABLE IF NOT EXISTS platform_settings (
    setting_key VARCHAR(60) NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Essai gratuit d'un mois activé par défaut (comportement actuel).
INSERT INTO platform_settings (setting_key, setting_value, updated_at)
SELECT 'trial_enabled', '1', UTC_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM platform_settings WHERE setting_key = 'trial_enabled');
