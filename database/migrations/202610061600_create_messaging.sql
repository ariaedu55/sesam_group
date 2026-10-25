CREATE TABLE IF NOT EXISTS message_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NULL,
    slug VARCHAR(60) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    name VARCHAR(120) NOT NULL,
    subject VARCHAR(190) NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_message_templates_scope (tenant_id, slug, channel),
    CONSTRAINT fk_message_templates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_deliveries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    channel VARCHAR(20) NOT NULL,
    recipient VARCHAR(190) NOT NULL,
    template_slug VARCHAR(60) NOT NULL,
    subject VARCHAR(190) NULL,
    body TEXT NOT NULL,
    status VARCHAR(20) NOT NULL,
    error_message VARCHAR(500) NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    sent_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_message_deliveries_tenant (tenant_id, created_at),
    KEY idx_message_deliveries_status (tenant_id, status),
    CONSTRAINT fk_message_deliveries_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_reminders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    channel VARCHAR(20) NOT NULL,
    recipient VARCHAR(190) NOT NULL,
    template_slug VARCHAR(60) NOT NULL,
    variables_json TEXT NOT NULL,
    send_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    last_error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_message_reminders_due (status, send_at),
    KEY idx_message_reminders_tenant (tenant_id),
    CONSTRAINT fk_message_reminders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name)
SELECT seed.slug, seed.name
FROM (
    SELECT 'notifications.send' AS slug, 'Envoyer des notifications' AS name
    UNION ALL SELECT 'notifications.history', 'Voir l''historique des envois'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.slug = seed.slug);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug IN ('notifications.send', 'notifications.history')
WHERE r.slug IN ('tenant-owner', 'manager')
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug = 'notifications.history'
WHERE r.slug = 'staff'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO message_templates (tenant_id, slug, channel, name, subject, body, created_at, updated_at)
SELECT NULL, seed.slug, seed.channel, seed.name, seed.subject, seed.body, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM (
    SELECT 'test_message' AS slug, 'internal' AS channel, 'Message interne test' AS name,
           NULL AS subject, 'Bonjour {{name}}, ceci est un test interne pour {{commerce}}.' AS body
    UNION ALL SELECT 'test_message', 'email', 'E-mail test', 'Test SESAM — {{commerce}}',
           'Bonjour {{name}}, message de test depuis {{commerce}}.'
    UNION ALL SELECT 'test_message', 'whatsapp', 'WhatsApp test', NULL,
           'Bonjour {{name}}, test WhatsApp pour {{commerce}}.'
    UNION ALL SELECT 'payment_confirmed', 'internal', 'Paiement confirmé (interne)', NULL,
           'Paiement de {{amount}} FCFA confirmé pour {{client}}.'
    UNION ALL SELECT 'payment_confirmed', 'email', 'Paiement confirmé (e-mail)', 'Paiement reçu — {{commerce}}',
           'Bonjour {{name}}, nous confirmons la réception de {{amount}} FCFA.'
    UNION ALL SELECT 'payment_confirmed', 'whatsapp', 'Paiement confirmé (WhatsApp)', NULL,
           'Paiement {{amount}} FCFA confirmé — {{commerce}}.'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM message_templates LIMIT 1);
