CREATE TABLE IF NOT EXISTS commande_products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    price_minor INT UNSIGNED NOT NULL DEFAULT 0,
    deposit_minor INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_commande_products_tenant (tenant_id),
    CONSTRAINT fk_commande_products_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commande_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    order_number VARCHAR(32) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    total_minor INT UNSIGNED NOT NULL DEFAULT 0,
    deposit_amount_minor INT UNSIGNED NOT NULL DEFAULT 0,
    payment_id INT UNSIGNED NULL,
    fabric_notes VARCHAR(500) NULL,
    instructions VARCHAR(500) NULL,
    photo_url VARCHAR(500) NULL,
    delivery_due_at DATETIME NULL,
    delivered_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_commande_orders_number (tenant_id, order_number),
    KEY idx_commande_orders_tenant_status (tenant_id, status),
    KEY idx_commande_orders_delivery (tenant_id, delivery_due_at),
    CONSTRAINT fk_commande_orders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_commande_orders_client FOREIGN KEY (client_id) REFERENCES clients (id),
    CONSTRAINT fk_commande_orders_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commande_order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(120) NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    unit_price_minor INT UNSIGNED NOT NULL DEFAULT 0,
    line_total_minor INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_commande_order_items_order (order_id),
    CONSTRAINT fk_commande_order_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_commande_order_items_order FOREIGN KEY (order_id) REFERENCES commande_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_commande_order_items_product FOREIGN KEY (product_id) REFERENCES commande_products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commande_order_measurements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    author_user_id INT UNSIGNED NULL,
    chest_cm DECIMAL(6, 2) NULL,
    waist_cm DECIMAL(6, 2) NULL,
    hips_cm DECIMAL(6, 2) NULL,
    length_cm DECIMAL(6, 2) NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_commande_measurements_order (order_id, created_at),
    CONSTRAINT fk_commande_measurements_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_commande_measurements_order FOREIGN KEY (order_id) REFERENCES commande_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_commande_measurements_author FOREIGN KEY (author_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS commande_deliveries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    due_at DATETIME NOT NULL,
    delivered_at DATETIME NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_commande_deliveries_due (tenant_id, due_at),
    CONSTRAINT fk_commande_deliveries_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_commande_deliveries_order FOREIGN KEY (order_id) REFERENCES commande_orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name)
SELECT seed.slug, seed.name
FROM (
    SELECT 'commande.view' AS slug, 'Voir le module Commande' AS name
    UNION ALL SELECT 'commande.products', 'Gérer les produits'
    UNION ALL SELECT 'commande.orders', 'Gérer les commandes'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.slug = seed.slug);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('commande.view', 'commande.products', 'commande.orders')
WHERE r.slug = 'tenant-owner'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('commande.view', 'commande.products', 'commande.orders')
WHERE r.slug = 'manager'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('commande.view', 'commande.orders')
WHERE r.slug = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
