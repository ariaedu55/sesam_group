-- Anciens liens publics (/t/{slug}) d'un commerce qui a changé de lien : ils redirigent vers le nouveau,
-- pour que les liens déjà partagés sur WhatsApp ou Instagram continuent de fonctionner.
CREATE TABLE IF NOT EXISTS tenant_slug_history (
    old_slug VARCHAR(80) NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (old_slug),
    KEY idx_tenant_slug_history_tenant (tenant_id),
    CONSTRAINT fk_tenant_slug_history_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
