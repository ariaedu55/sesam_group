CREATE TABLE IF NOT EXISTS inscription_classes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_inscription_classes_tenant (tenant_id),
    CONSTRAINT fk_inscription_classes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inscription_fee_types (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    total_minor INT UNSIGNED NOT NULL DEFAULT 0,
    installment_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_inscription_fee_types_tenant (tenant_id),
    CONSTRAINT fk_inscription_fee_types_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inscription_enrollments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    student_name VARCHAR(120) NOT NULL,
    guardian_client_id INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_inscription_enrollments_tenant (tenant_id, created_at),
    CONSTRAINT fk_inscription_enrollments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_inscription_enrollments_class FOREIGN KEY (class_id) REFERENCES inscription_classes (id),
    CONSTRAINT fk_inscription_enrollments_guardian FOREIGN KEY (guardian_client_id) REFERENCES clients (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inscription_installments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    enrollment_id INT UNSIGNED NOT NULL,
    fee_type_id INT UNSIGNED NOT NULL,
    label VARCHAR(80) NOT NULL,
    amount_minor INT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    payment_id INT UNSIGNED NULL,
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_inscription_installments_due (tenant_id, due_date, status),
    CONSTRAINT fk_inscription_installments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_inscription_installments_enrollment FOREIGN KEY (enrollment_id) REFERENCES inscription_enrollments (id) ON DELETE CASCADE,
    CONSTRAINT fk_inscription_installments_fee FOREIGN KEY (fee_type_id) REFERENCES inscription_fee_types (id),
    CONSTRAINT fk_inscription_installments_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name)
SELECT seed.slug, seed.name
FROM (
    SELECT 'inscription.view' AS slug, 'Voir le module Inscription' AS name
    UNION ALL SELECT 'inscription.classes', 'Gérer les classes'
    UNION ALL SELECT 'inscription.fees', 'Gérer les types de frais'
    UNION ALL SELECT 'inscription.enrollments', 'Gérer les inscriptions'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.slug = seed.slug);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN (
    'inscription.view', 'inscription.classes', 'inscription.fees', 'inscription.enrollments'
)
WHERE r.slug = 'tenant-owner'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN (
    'inscription.view', 'inscription.classes', 'inscription.fees', 'inscription.enrollments'
)
WHERE r.slug = 'manager'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('inscription.view', 'inscription.enrollments')
WHERE r.slug = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
