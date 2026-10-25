CREATE TABLE IF NOT EXISTS boutique_settings (
    tenant_id INT UNSIGNED NOT NULL,
    whatsapp_phone VARCHAR(30) NULL,
    tagline VARCHAR(160) NULL,
    description VARCHAR(1000) NULL,
    address VARCHAR(190) NULL,
    opening_hours VARCHAR(190) NULL,
    delivery_info VARCHAR(300) NULL,
    instagram_url VARCHAR(255) NULL,
    facebook_url VARCHAR(255) NULL,
    tiktok_url VARCHAR(255) NULL,
    logo_path VARCHAR(80) NULL,
    cover_path VARCHAR(80) NULL,
    theme VARCHAR(20) NOT NULL DEFAULT 'emeraude',
    is_open TINYINT(1) NOT NULL DEFAULT 1,
    show_stock TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (tenant_id),
    CONSTRAINT fk_boutique_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boutique_products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(1000) NULL,
    category VARCHAR(60) NULL,
    price_minor INT UNSIGNED NOT NULL DEFAULT 0,
    compare_price_minor INT UNSIGNED NULL,
    track_stock TINYINT(1) NOT NULL DEFAULT 1,
    stock_quantity INT NOT NULL DEFAULT 0,
    low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 3,
    image_path VARCHAR(80) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_boutique_products_tenant (tenant_id, is_published),
    KEY idx_boutique_products_category (tenant_id, category),
    CONSTRAINT fk_boutique_products_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boutique_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NULL,
    order_number VARCHAR(32) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    source VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
    customer_name VARCHAR(120) NOT NULL,
    customer_phone VARCHAR(40) NULL,
    customer_address VARCHAR(255) NULL,
    note VARCHAR(500) NULL,
    total_minor INT UNSIGNED NOT NULL DEFAULT 0,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    stock_applied TINYINT(1) NOT NULL DEFAULT 0,
    confirmed_at DATETIME NULL,
    delivered_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_boutique_orders_number (tenant_id, order_number),
    KEY idx_boutique_orders_status (tenant_id, status, created_at),
    KEY idx_boutique_orders_client (tenant_id, client_id),
    CONSTRAINT fk_boutique_orders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_boutique_orders_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boutique_order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    product_name VARCHAR(120) NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    unit_price_minor INT UNSIGNED NOT NULL DEFAULT 0,
    line_total_minor INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_boutique_order_items_order (order_id),
    KEY idx_boutique_order_items_product (product_id),
    CONSTRAINT fk_boutique_order_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_boutique_order_items_order FOREIGN KEY (order_id) REFERENCES boutique_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_boutique_order_items_product FOREIGN KEY (product_id) REFERENCES boutique_products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boutique_stock_movements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    type VARCHAR(20) NOT NULL,
    quantity INT NOT NULL,
    stock_after INT NOT NULL,
    note VARCHAR(255) NULL,
    order_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_boutique_movements_tenant (tenant_id, created_at),
    KEY idx_boutique_movements_product (product_id, created_at),
    CONSTRAINT fk_boutique_movements_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_boutique_movements_product FOREIGN KEY (product_id) REFERENCES boutique_products (id) ON DELETE CASCADE,
    CONSTRAINT fk_boutique_movements_order FOREIGN KEY (order_id) REFERENCES boutique_orders (id) ON DELETE SET NULL,
    CONSTRAINT fk_boutique_movements_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name)
SELECT seed.slug, seed.name
FROM (
    SELECT 'boutique.view' AS slug, 'Voir la boutique' AS name
    UNION ALL SELECT 'boutique.products', 'Gérer les produits et le stock'
    UNION ALL SELECT 'boutique.orders', 'Gérer les commandes de la boutique'
    UNION ALL SELECT 'boutique.settings', 'Modifier la boutique (logo, WhatsApp, présentation)'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.slug = seed.slug);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('boutique.view', 'boutique.products', 'boutique.orders', 'boutique.settings')
WHERE r.slug IN ('tenant-owner', 'manager')
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('boutique.view', 'boutique.orders')
WHERE r.slug = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
