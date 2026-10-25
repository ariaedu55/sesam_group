CREATE TABLE IF NOT EXISTS garde_staff (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    role_label VARCHAR(80) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_garde_staff_tenant (tenant_id),
    CONSTRAINT fk_garde_staff_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS garde_shifts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    shift_date DATE NOT NULL,
    note VARCHAR(180) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_garde_shifts_day (tenant_id, staff_id, shift_date),
    KEY idx_garde_shifts_date (tenant_id, shift_date),
    CONSTRAINT fk_garde_shifts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_garde_shifts_staff FOREIGN KEY (staff_id) REFERENCES garde_staff (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS garde_products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    unit VARCHAR(40) NOT NULL DEFAULT 'unité',
    min_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_garde_products_tenant (tenant_id),
    CONSTRAINT fk_garde_products_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS garde_batches (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    lot_code VARCHAR(80) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    expires_on DATE NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_garde_lot (tenant_id, product_id, lot_code),
    KEY idx_garde_batches_expiry (tenant_id, expires_on),
    KEY idx_garde_batches_product (tenant_id, product_id),
    CONSTRAINT fk_garde_batches_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_garde_batches_product FOREIGN KEY (product_id) REFERENCES garde_products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS garde_movements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL,
    type VARCHAR(20) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    reason VARCHAR(180) NOT NULL DEFAULT '',
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_garde_movements_tenant (tenant_id, created_at),
    CONSTRAINT fk_garde_movements_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_garde_movements_product FOREIGN KEY (product_id) REFERENCES garde_products (id),
    CONSTRAINT fk_garde_movements_batch FOREIGN KEY (batch_id) REFERENCES garde_batches (id),
    CONSTRAINT fk_garde_movements_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name)
SELECT seed.slug, seed.name
FROM (
    SELECT 'garde.view' AS slug, 'Voir le module Garde' AS name
    UNION ALL SELECT 'garde.staff', 'Gérer le personnel de garde'
    UNION ALL SELECT 'garde.shifts', 'Planifier les tours de garde'
    UNION ALL SELECT 'garde.stock', 'Gérer le stock et les lots'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.slug = seed.slug);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('garde.view', 'garde.staff', 'garde.shifts', 'garde.stock')
WHERE r.slug = 'tenant-owner'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('garde.view', 'garde.staff', 'garde.shifts', 'garde.stock')
WHERE r.slug = 'manager'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('garde.view', 'garde.shifts', 'garde.stock')
WHERE r.slug = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
