CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    sector VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tenants_slug (slug),
    KEY idx_tenants_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenant_settings (
    tenant_id INT UNSIGNED NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (tenant_id),
    CONSTRAINT fk_tenant_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id,
    ADD KEY idx_users_tenant (tenant_id),
    ADD CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id);

INSERT INTO permissions (slug, name)
SELECT seed.slug, seed.name
FROM (
    SELECT 'tenants.view' AS slug, 'Voir les commerces' AS name
    UNION ALL SELECT 'tenants.create', 'Créer un commerce'
    UNION ALL SELECT 'tenants.update', 'Modifier un commerce'
    UNION ALL SELECT 'settings.view', 'Voir les paramètres du commerce'
    UNION ALL SELECT 'settings.update', 'Modifier les paramètres du commerce'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.slug = seed.slug);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('tenants.view', 'tenants.create', 'tenants.update')
WHERE r.slug IN ('super-admin', 'admin')
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN (
    'users.view', 'users.create', 'users.update', 'users.deactivate',
    'users.set_password', 'users.assign_roles',
    'settings.view', 'settings.update'
)
WHERE r.slug = 'tenant-owner'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('settings.view', 'settings.update')
WHERE r.slug = 'manager'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug = 'settings.view'
WHERE r.slug = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
