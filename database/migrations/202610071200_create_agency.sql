CREATE TABLE IF NOT EXISTS agency_prospects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    business_name VARCHAR(120) NOT NULL DEFAULT '',
    email VARCHAR(190) NOT NULL DEFAULT '',
    phone VARCHAR(30) NOT NULL DEFAULT '',
    activity VARCHAR(40) NOT NULL,
    source VARCHAR(20) NOT NULL,
    contact_request_id INT UNSIGNED NULL,
    stage VARCHAR(30) NOT NULL,
    next_action_on DATE NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_agency_prospects_request (contact_request_id),
    KEY idx_agency_prospects_stage (stage, updated_at),
    CONSTRAINT fk_agency_prospects_request FOREIGN KEY (contact_request_id) REFERENCES contact_requests (id) ON DELETE SET NULL,
    CONSTRAINT fk_agency_prospects_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agency_prospect_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    prospect_id INT UNSIGNED NOT NULL,
    kind VARCHAR(20) NOT NULL,
    body VARCHAR(500) NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_agency_prospect_events_prospect (prospect_id, created_at),
    CONSTRAINT fk_agency_prospect_events_prospect FOREIGN KEY (prospect_id) REFERENCES agency_prospects (id) ON DELETE CASCADE,
    CONSTRAINT fk_agency_prospect_events_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agency_quotes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    prospect_id INT UNSIGNED NOT NULL,
    reference VARCHAR(20) NOT NULL,
    offer VARCHAR(20) NOT NULL,
    site_path VARCHAR(20) NOT NULL,
    amount INT UNSIGNED NOT NULL,
    description VARCHAR(2000) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL,
    sent_at DATETIME NULL,
    decided_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_agency_quotes_reference (reference),
    KEY idx_agency_quotes_prospect (prospect_id),
    CONSTRAINT fk_agency_quotes_prospect FOREIGN KEY (prospect_id) REFERENCES agency_prospects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agency_projects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    prospect_id INT UNSIGNED NOT NULL,
    quote_id INT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    offer VARCHAR(20) NOT NULL,
    amount INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL,
    start_month CHAR(7) NULL,
    started_on DATE NULL,
    delivered_on DATE NULL,
    site_url VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_agency_projects_quote (quote_id),
    KEY idx_agency_projects_month (start_month),
    KEY idx_agency_projects_status (status),
    CONSTRAINT fk_agency_projects_prospect FOREIGN KEY (prospect_id) REFERENCES agency_prospects (id) ON DELETE CASCADE,
    CONSTRAINT fk_agency_projects_quote FOREIGN KEY (quote_id) REFERENCES agency_quotes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agency_invoices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    reference VARCHAR(20) NOT NULL,
    label VARCHAR(120) NOT NULL,
    amount INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL,
    due_on DATE NOT NULL,
    paid_on DATE NULL,
    payment_method VARCHAR(20) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_agency_invoices_reference (reference),
    KEY idx_agency_invoices_project (project_id),
    KEY idx_agency_invoices_status (status, due_on),
    CONSTRAINT fk_agency_invoices_project FOREIGN KEY (project_id) REFERENCES agency_projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name)
SELECT seed.slug, seed.name
FROM (
    SELECT 'agency.view' AS slug, 'Voir l''agence (pipeline, projets, factures)' AS name
    UNION ALL SELECT 'agency.prospects', 'Gérer les prospects'
    UNION ALL SELECT 'agency.quotes', 'Gérer les devis'
    UNION ALL SELECT 'agency.projects', 'Gérer les projets'
    UNION ALL SELECT 'agency.invoices', 'Gérer les factures et paiements agence'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.slug = seed.slug);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('agency.view', 'agency.prospects', 'agency.quotes', 'agency.projects', 'agency.invoices')
WHERE r.slug IN ('super-admin', 'admin')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('agency.view', 'agency.prospects', 'agency.quotes')
WHERE r.slug = 'commercial'
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('agency.view', 'agency.invoices')
WHERE r.slug = 'finance'
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);
