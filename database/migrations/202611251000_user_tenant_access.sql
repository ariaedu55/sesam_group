-- Un même compte (e-mail ou téléphone) peut ouvrir plusieurs activités SESAM (Immo, Réservation, Boutique).
-- users.tenant_id reste l'activité principale du compte ; cette table liste les activités supplémentaires.
CREATE TABLE IF NOT EXISTS user_tenant_access (
    user_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, tenant_id),
    KEY idx_user_tenant_access_tenant (tenant_id),
    CONSTRAINT fk_user_tenant_access_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_tenant_access_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
