-- Console SESAM : encaissements des abonnements SaaS, notes internes sur les commerces, tarifs par module.

CREATE TABLE IF NOT EXISTS tenant_subscription_payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    amount INT UNSIGNED NOT NULL,
    months TINYINT UNSIGNED NOT NULL DEFAULT 1,
    paid_on DATE NOT NULL,
    method VARCHAR(20) NOT NULL,
    reference VARCHAR(80) NOT NULL DEFAULT '',
    period_end DATE NULL,
    note VARCHAR(180) NOT NULL DEFAULT '',
    actor_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_tenant_sub_payments_paid (paid_on),
    KEY idx_tenant_sub_payments_tenant (tenant_id, paid_on),
    CONSTRAINT fk_tenant_sub_payments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_tenant_sub_payments_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    body VARCHAR(1000) NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_tenant_notes_tenant (tenant_id, created_at),
    CONSTRAINT fk_tenant_notes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_tenant_notes_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tarif mensuel proposé par défaut pour chaque module, repris à l'activation d'un abonnement.
CREATE TABLE IF NOT EXISTS saas_prices (
    sector VARCHAR(40) NOT NULL,
    monthly_amount INT UNSIGNED NOT NULL,
    setup_fee INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (sector)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO saas_prices (sector, monthly_amount, setup_fee, updated_at)
SELECT seed.sector, seed.amount, 0, UTC_TIMESTAMP()
FROM (
    SELECT 'reservation' AS sector, 15000 AS amount
    UNION ALL SELECT 'boutique', 10000
    UNION ALL SELECT 'loyer', 15000
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM saas_prices p WHERE p.sector = seed.sector);

INSERT INTO permissions (slug, name)
SELECT 'revenue.view', 'Voir les revenus de SESAM (abonnements et agence)'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'revenue.view');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug = 'revenue.view'
WHERE r.slug IN ('super-admin', 'admin', 'finance')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

-- La finance enregistre aussi les encaissements d'abonnement.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('subscriptions.view', 'subscriptions.manage', 'tenants.view')
WHERE r.slug = 'finance'
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);
