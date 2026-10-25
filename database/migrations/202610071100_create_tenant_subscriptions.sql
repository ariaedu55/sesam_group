CREATE TABLE IF NOT EXISTS tenant_subscriptions (
    tenant_id INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL,
    monthly_amount_minor INT UNSIGNED NULL,
    period_start DATE NULL,
    period_end DATE NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (tenant_id),
    KEY idx_tenant_subscriptions_status (status, period_end),
    CONSTRAINT fk_tenant_subscriptions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_subscription_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    action VARCHAR(20) NOT NULL,
    from_status VARCHAR(20) NULL,
    to_status VARCHAR(20) NOT NULL,
    period_end DATE NULL,
    note VARCHAR(180) NOT NULL DEFAULT '',
    actor_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_tenant_subscription_events_tenant (tenant_id, created_at),
    CONSTRAINT fk_tenant_subscription_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_tenant_subscription_events_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name)
SELECT seed.slug, seed.name
FROM (
    SELECT 'subscriptions.view' AS slug, 'Voir les abonnements des commerces' AS name
    UNION ALL SELECT 'subscriptions.manage', 'Activer, suspendre ou renouveler un abonnement'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.slug = seed.slug);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('subscriptions.view', 'subscriptions.manage')
WHERE r.slug IN ('super-admin', 'admin')
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
