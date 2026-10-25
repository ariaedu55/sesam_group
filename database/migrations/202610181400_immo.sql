-- Module Immo (ex-Loyer) : fiches biens et logements détaillées, baux complets (charges, caution, préavis, fin),
-- paiements partiels, quittances, relances et alertes réglables, interventions, fiches locataires, personnalisation.
-- Les tables loyer_* sont gardées (aucune perte de données) et complétées.

ALTER TABLE loyer_properties
    ADD COLUMN kind VARCHAR(30) NOT NULL DEFAULT 'immeuble' AFTER name,
    ADD COLUMN city VARCHAR(80) NULL AFTER address,
    ADD COLUMN owner_name VARCHAR(120) NULL AFTER city,
    ADD COLUMN owner_phone VARCHAR(40) NULL AFTER owner_name,
    ADD COLUMN notes VARCHAR(1000) NULL AFTER owner_phone;

ALTER TABLE loyer_units
    ADD COLUMN kind VARCHAR(30) NOT NULL DEFAULT 'appartement' AFTER label,
    ADD COLUMN rooms TINYINT UNSIGNED NULL AFTER kind,
    ADD COLUMN surface_m2 SMALLINT UNSIGNED NULL AFTER rooms,
    ADD COLUMN floor_label VARCHAR(30) NULL AFTER surface_m2,
    ADD COLUMN default_charges_minor INT UNSIGNED NOT NULL DEFAULT 0 AFTER default_rent_minor,
    ADD COLUMN default_deposit_minor INT UNSIGNED NOT NULL DEFAULT 0 AFTER default_charges_minor,
    ADD COLUMN description VARCHAR(500) NULL AFTER default_deposit_minor;

ALTER TABLE loyer_leases
    ADD COLUMN reference VARCHAR(20) NULL AFTER id,
    ADD COLUMN charges_minor INT UNSIGNED NOT NULL DEFAULT 0 AFTER rent_minor,
    ADD COLUMN deposit_minor INT UNSIGNED NOT NULL DEFAULT 0 AFTER charges_minor,
    ADD COLUMN deposit_paid_on DATE NULL AFTER deposit_minor,
    ADD COLUMN notice_months TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER due_day,
    ADD COLUMN ended_on DATE NULL AFTER ends_on,
    ADD COLUMN end_reason VARCHAR(255) NULL AFTER ended_on,
    ADD COLUMN notes VARCHAR(1000) NULL AFTER end_reason;

ALTER TABLE loyer_rent_dues
    ADD COLUMN rent_part_minor INT UNSIGNED NOT NULL DEFAULT 0 AFTER amount_minor,
    ADD COLUMN charges_part_minor INT UNSIGNED NOT NULL DEFAULT 0 AFTER rent_part_minor,
    ADD COLUMN paid_minor INT UNSIGNED NOT NULL DEFAULT 0 AFTER charges_part_minor,
    ADD COLUMN last_reminder_at DATETIME NULL AFTER paid_at,
    ADD COLUMN reminder_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER last_reminder_at;

-- Les échéances existantes : loyer seul, et montant déjà payé pour celles réglées.
UPDATE loyer_rent_dues SET rent_part_minor = amount_minor WHERE rent_part_minor = 0;
UPDATE loyer_rent_dues SET paid_minor = amount_minor WHERE status = 'paid' AND paid_minor = 0;
UPDATE loyer_leases SET reference = CONCAT('BAIL-', LPAD(id, 5, '0')) WHERE reference IS NULL;

-- Chaque encaissement d'une échéance (paiement complet ou partiel, en main propre ou en ligne).
CREATE TABLE IF NOT EXISTS immo_payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    due_id INT UNSIGNED NOT NULL,
    lease_id INT UNSIGNED NOT NULL,
    amount_minor INT UNSIGNED NOT NULL,
    method VARCHAR(20) NOT NULL,
    reference VARCHAR(80) NULL,
    paid_on DATE NOT NULL,
    note VARCHAR(255) NULL,
    payment_id INT UNSIGNED NULL,
    receipt_number VARCHAR(30) NULL,
    actor_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_immo_payments_online (payment_id),
    KEY idx_immo_payments_due (due_id),
    KEY idx_immo_payments_tenant_date (tenant_id, paid_on),
    CONSTRAINT fk_immo_payments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_immo_payments_due FOREIGN KEY (due_id) REFERENCES loyer_rent_dues (id) ON DELETE CASCADE,
    CONSTRAINT fk_immo_payments_lease FOREIGN KEY (lease_id) REFERENCES loyer_leases (id) ON DELETE CASCADE,
    CONSTRAINT fk_immo_payments_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reprise de l'historique : une ligne d'encaissement pour chaque échéance déjà payée.
INSERT INTO immo_payments (tenant_id, due_id, lease_id, amount_minor, method, reference, paid_on, note, payment_id, receipt_number, actor_user_id, created_at)
SELECT d.tenant_id, d.id, d.lease_id, d.amount_minor,
       IF(d.payment_id IS NULL, 'especes', 'en-ligne'), NULL, DATE(COALESCE(d.paid_at, d.updated_at)), NULL,
       IF(p.status = 'success', d.payment_id, NULL), CONCAT('Q-', LPAD(d.id, 6, '0')), NULL, COALESCE(d.paid_at, d.updated_at)
FROM loyer_rent_dues d
LEFT JOIN payments p ON p.id = d.payment_id
WHERE d.status = 'paid'
  AND NOT EXISTS (SELECT 1 FROM immo_payments ip WHERE ip.due_id = d.id);

-- Réglages et personnalisation de l'agence / du propriétaire.
CREATE TABLE IF NOT EXISTS immo_settings (
    tenant_id INT UNSIGNED NOT NULL,
    display_name VARCHAR(120) NULL,
    whatsapp_phone VARCHAR(20) NULL,
    email VARCHAR(190) NULL,
    address VARCHAR(255) NULL,
    receipt_footer VARCHAR(500) NULL,
    signature_name VARCHAR(120) NULL,
    default_due_day TINYINT UNSIGNED NOT NULL DEFAULT 5,
    grace_days TINYINT UNSIGNED NOT NULL DEFAULT 3,
    remind_before_days TINYINT UNSIGNED NOT NULL DEFAULT 3,
    remind_on_due TINYINT(1) NOT NULL DEFAULT 1,
    remind_after_days VARCHAR(40) NOT NULL DEFAULT '3,7,15',
    auto_reminders TINYINT(1) NOT NULL DEFAULT 1,
    reminder_message VARCHAR(600) NULL,
    allow_partial TINYINT(1) NOT NULL DEFAULT 1,
    min_partial_minor INT UNSIGNED NOT NULL DEFAULT 5000,
    lease_end_alert_days SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (tenant_id),
    CONSTRAINT fk_immo_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des relances (automatiques ou envoyées à la main).
CREATE TABLE IF NOT EXISTS immo_reminders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    due_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NULL,
    kind VARCHAR(20) NOT NULL,
    channel VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
    recipient VARCHAR(60) NULL,
    message VARCHAR(800) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'sent',
    actor_user_id INT UNSIGNED NULL,
    sent_on DATE NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_immo_reminders_auto (due_id, kind, sent_on),
    KEY idx_immo_reminders_tenant (tenant_id, created_at),
    CONSTRAINT fk_immo_reminders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_immo_reminders_due FOREIGN KEY (due_id) REFERENCES loyer_rent_dues (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Interventions et travaux (fuite, peinture, électricité…), avec coût.
CREATE TABLE IF NOT EXISTS immo_interventions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    property_id INT UNSIGNED NOT NULL,
    unit_id INT UNSIGNED NULL,
    title VARCHAR(160) NOT NULL,
    description VARCHAR(1000) NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'normale',
    status VARCHAR(20) NOT NULL DEFAULT 'ouverte',
    provider VARCHAR(120) NULL,
    cost_minor INT UNSIGNED NOT NULL DEFAULT 0,
    reported_on DATE NOT NULL,
    done_on DATE NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_immo_interventions_tenant_status (tenant_id, status),
    CONSTRAINT fk_immo_interventions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_immo_interventions_property FOREIGN KEY (property_id) REFERENCES loyer_properties (id) ON DELETE CASCADE,
    CONSTRAINT fk_immo_interventions_unit FOREIGN KEY (unit_id) REFERENCES loyer_units (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fiche locataire : complète la fiche client (pièce d'identité, profession, contact d'urgence, garant).
CREATE TABLE IF NOT EXISTS immo_tenant_profiles (
    client_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    id_type VARCHAR(30) NULL,
    id_number VARCHAR(60) NULL,
    profession VARCHAR(120) NULL,
    employer VARCHAR(120) NULL,
    emergency_name VARCHAR(120) NULL,
    emergency_phone VARCHAR(40) NULL,
    guarantor_name VARCHAR(120) NULL,
    guarantor_phone VARCHAR(40) NULL,
    notes VARCHAR(1000) NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (client_id),
    KEY idx_immo_profiles_tenant (tenant_id),
    CONSTRAINT fk_immo_profiles_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE,
    CONSTRAINT fk_immo_profiles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réglages Immo : propriétaire et gérant.
INSERT IGNORE INTO permissions (slug, name)
VALUES ('loyer.settings', 'Immo : réglages, relances et personnalisation');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug = 'loyer.settings' WHERE r.slug IN ('tenant-owner', 'manager');
