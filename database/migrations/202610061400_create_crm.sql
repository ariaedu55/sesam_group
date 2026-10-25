CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_clients_tenant (tenant_id),
    KEY idx_clients_tenant_status (tenant_id, status),
    KEY idx_clients_tenant_name (tenant_id, full_name),
    CONSTRAINT fk_clients_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    author_user_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_client_notes_client (client_id, created_at),
    KEY idx_client_notes_tenant (tenant_id),
    CONSTRAINT fk_client_notes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_client_notes_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_client_notes_author FOREIGN KEY (author_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(60) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_client_tags_tenant_name (tenant_id, name),
    CONSTRAINT fk_client_tags_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_tags_map (
    client_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (client_id, tag_id),
    KEY idx_client_tags_map_tenant (tenant_id),
    CONSTRAINT fk_ctm_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_ctm_tag FOREIGN KEY (tag_id) REFERENCES client_tags (id) ON DELETE CASCADE,
    CONSTRAINT fk_ctm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_activities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    kind VARCHAR(40) NOT NULL,
    summary VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_client_activities_tenant_created (tenant_id, created_at),
    KEY idx_client_activities_client (client_id, created_at),
    CONSTRAINT fk_client_activities_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_client_activities_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_client_activities_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name)
SELECT seed.slug, seed.name
FROM (
    SELECT 'clients.view' AS slug, 'Voir les clients' AS name
    UNION ALL SELECT 'clients.create', 'Créer un client'
    UNION ALL SELECT 'clients.update', 'Modifier un client'
    UNION ALL SELECT 'clients.archive', 'Archiver ou restaurer un client'
    UNION ALL SELECT 'clients.export', 'Exporter la liste clients'
    UNION ALL SELECT 'clients.notes', 'Ajouter des notes client'
    UNION ALL SELECT 'clients.tags', 'Gérer les tags client'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.slug = seed.slug);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN (
    'clients.view', 'clients.create', 'clients.update', 'clients.archive',
    'clients.export', 'clients.notes', 'clients.tags'
)
WHERE r.slug = 'tenant-owner'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN (
    'clients.view', 'clients.create', 'clients.update', 'clients.export',
    'clients.notes', 'clients.tags'
)
WHERE r.slug = 'manager'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('clients.view', 'clients.notes')
WHERE r.slug = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
