CREATE TABLE IF NOT EXISTS reservation_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reservation_categories_tenant (tenant_id),
    CONSTRAINT fk_reservation_categories_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_services (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    price_minor INT UNSIGNED NOT NULL DEFAULT 0,
    deposit_minor INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reservation_services_tenant (tenant_id),
    CONSTRAINT fk_reservation_services_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_services_category FOREIGN KEY (category_id) REFERENCES reservation_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_staff (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reservation_staff_tenant (tenant_id),
    CONSTRAINT fk_reservation_staff_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_staff_availability (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    starts_at TIME NOT NULL,
    ends_at TIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reservation_availability_staff (staff_id, weekday),
    CONSTRAINT fk_reservation_availability_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_availability_staff FOREIGN KEY (staff_id) REFERENCES reservation_staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_appointments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NULL,
    starts_at DATETIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    deposit_amount_minor INT UNSIGNED NOT NULL DEFAULT 0,
    payment_id INT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    cancelled_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reservation_appointments_tenant_starts (tenant_id, starts_at),
    KEY idx_reservation_appointments_staff (staff_id, starts_at),
    CONSTRAINT fk_reservation_appointments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_appointments_client FOREIGN KEY (client_id) REFERENCES clients (id),
    CONSTRAINT fk_reservation_appointments_service FOREIGN KEY (service_id) REFERENCES reservation_services (id),
    CONSTRAINT fk_reservation_appointments_staff FOREIGN KEY (staff_id) REFERENCES reservation_staff (id) ON DELETE SET NULL,
    CONSTRAINT fk_reservation_appointments_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name)
SELECT seed.slug, seed.name
FROM (
    SELECT 'reservation.view' AS slug, 'Voir le module Réservation' AS name
    UNION ALL SELECT 'reservation.services', 'Gérer les prestations'
    UNION ALL SELECT 'reservation.staff', 'Gérer le personnel'
    UNION ALL SELECT 'reservation.appointments', 'Gérer les rendez-vous'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.slug = seed.slug);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN (
    'reservation.view', 'reservation.services', 'reservation.staff', 'reservation.appointments'
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
    'reservation.view', 'reservation.services', 'reservation.staff', 'reservation.appointments'
)
WHERE r.slug = 'manager'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('reservation.view', 'reservation.appointments')
WHERE r.slug = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
