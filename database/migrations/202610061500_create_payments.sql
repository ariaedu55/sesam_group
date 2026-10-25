CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NULL,
    reference CHAR(32) NOT NULL,
    amount_minor INT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'XOF',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    gateway VARCHAR(40) NOT NULL DEFAULT 'naboopay',
    mode VARCHAR(20) NOT NULL DEFAULT 'sandbox',
    description VARCHAR(255) NOT NULL,
    external_id VARCHAR(120) NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_reference (reference),
    KEY idx_payments_tenant_status (tenant_id, status),
    KEY idx_payments_tenant_created (tenant_id, created_at),
    CONSTRAINT fk_payments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    kind VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL,
    external_id VARCHAR(120) NULL,
    payload TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_payment_tx_payment (payment_id, created_at),
    KEY idx_payment_tx_tenant (tenant_id),
    CONSTRAINT fk_payment_tx_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE,
    CONSTRAINT fk_payment_tx_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_webhook_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(40) NOT NULL,
    event_id VARCHAR(120) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_webhook_event (provider, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name)
SELECT seed.slug, seed.name
FROM (
    SELECT 'payments.view' AS slug, 'Voir les paiements' AS name
    UNION ALL SELECT 'payments.create', 'Initier un paiement'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.slug = seed.slug);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('payments.view', 'payments.create')
WHERE r.slug IN ('tenant-owner', 'manager')
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug = 'payments.view'
WHERE r.slug = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
