-- Module Réservation complet : réglages du salon, horaires, fermetures et absences,
-- prestations détaillées, équipe détaillée, suivi des rendez-vous (terminé, absent, payé).

ALTER TABLE reservation_services
    ADD COLUMN description VARCHAR(500) NULL AFTER name,
    ADD COLUMN category VARCHAR(60) NULL AFTER description;

ALTER TABLE reservation_staff
    ADD COLUMN role_label VARCHAR(80) NULL AFTER name,
    ADD COLUMN phone VARCHAR(40) NULL AFTER role_label;

ALTER TABLE reservation_appointments
    ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER status,
    ADD COLUMN price_minor INT UNSIGNED NOT NULL DEFAULT 0 AFTER duration_minutes,
    ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_id,
    ADD COLUMN completed_at DATETIME NULL AFTER cancelled_at;

UPDATE reservation_appointments a
INNER JOIN reservation_services s ON s.id = a.service_id
SET a.price_minor = s.price_minor
WHERE a.price_minor = 0;

CREATE TABLE IF NOT EXISTS reservation_staff_services (
    tenant_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (staff_id, service_id),
    KEY idx_reservation_staff_services_service (service_id),
    CONSTRAINT fk_reservation_staff_services_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_staff_services_staff FOREIGN KEY (staff_id) REFERENCES reservation_staff (id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_staff_services_service FOREIGN KEY (service_id) REFERENCES reservation_services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_settings (
    tenant_id INT UNSIGNED NOT NULL,
    whatsapp_phone VARCHAR(30) NULL,
    tagline VARCHAR(160) NULL,
    description VARCHAR(1000) NULL,
    address VARCHAR(190) NULL,
    logo_path VARCHAR(80) NULL,
    cover_path VARCHAR(80) NULL,
    theme VARCHAR(20) NOT NULL DEFAULT 'ocean',
    is_open TINYINT(1) NOT NULL DEFAULT 1,
    auto_confirm TINYINT(1) NOT NULL DEFAULT 0,
    slot_step_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    min_notice_hours SMALLINT UNSIGNED NOT NULL DEFAULT 2,
    max_advance_days SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    buffer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    cancellation_policy VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (tenant_id),
    CONSTRAINT fk_reservation_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservation_hours (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    opens_at TIME NOT NULL,
    closes_at TIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reservation_hours_tenant (tenant_id, weekday),
    CONSTRAINT fk_reservation_hours_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fermeture du salon (staff_id NULL) ou absence d'une personne de l'équipe.
CREATE TABLE IF NOT EXISTS reservation_closures (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NULL,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    reason VARCHAR(160) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reservation_closures_tenant (tenant_id, starts_on, ends_on),
    CONSTRAINT fk_reservation_closures_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_reservation_closures_staff FOREIGN KEY (staff_id) REFERENCES reservation_staff (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name)
SELECT 'reservation.settings', 'Modifier le salon (horaires, règles, page publique)'
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'reservation.settings');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug = 'reservation.settings'
WHERE r.slug IN ('tenant-owner', 'manager')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);
