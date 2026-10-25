CREATE TABLE IF NOT EXISTS loyer_properties (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    address VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_loyer_properties_tenant (tenant_id),
    CONSTRAINT fk_loyer_properties_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyer_units (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    property_id INT UNSIGNED NOT NULL,
    label VARCHAR(80) NOT NULL,
    default_rent_minor INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_loyer_units_property (property_id),
    CONSTRAINT fk_loyer_units_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_loyer_units_property FOREIGN KEY (property_id) REFERENCES loyer_properties (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyer_leases (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    unit_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    rent_minor INT UNSIGNED NOT NULL,
    due_day TINYINT UNSIGNED NOT NULL DEFAULT 5,
    starts_on DATE NOT NULL,
    ends_on DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_loyer_leases_tenant_status (tenant_id, status),
    CONSTRAINT fk_loyer_leases_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_loyer_leases_unit FOREIGN KEY (unit_id) REFERENCES loyer_units (id),
    CONSTRAINT fk_loyer_leases_client FOREIGN KEY (client_id) REFERENCES clients (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyer_rent_dues (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    lease_id INT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    amount_minor INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    payment_id INT UNSIGNED NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_loyer_dues_tenant_date (tenant_id, due_date),
    KEY idx_loyer_dues_lease (lease_id),
    CONSTRAINT fk_loyer_dues_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_loyer_dues_lease FOREIGN KEY (lease_id) REFERENCES loyer_leases (id) ON DELETE CASCADE,
    CONSTRAINT fk_loyer_dues_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name)
SELECT seed.slug, seed.name
FROM (
    SELECT 'loyer.view' AS slug, 'Voir le module Loyer' AS name
    UNION ALL SELECT 'loyer.properties', 'Gérer biens et unités'
    UNION ALL SELECT 'loyer.leases', 'Gérer les contrats'
    UNION ALL SELECT 'loyer.dues', 'Gérer les échéances'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.slug = seed.slug);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('loyer.view', 'loyer.properties', 'loyer.leases', 'loyer.dues')
WHERE r.slug = 'tenant-owner'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('loyer.view', 'loyer.properties', 'loyer.leases', 'loyer.dues')
WHERE r.slug = 'manager'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('loyer.view', 'loyer.dues')
WHERE r.slug = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
