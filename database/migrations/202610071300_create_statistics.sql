ALTER TABLE payments
    ADD KEY idx_payments_tenant_paid (tenant_id, status, paid_at);

INSERT INTO permissions (slug, name)
SELECT seed.slug, seed.name
FROM (
    SELECT 'stats.view' AS slug, 'Voir les statistiques du commerce' AS name
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.slug = seed.slug);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.slug = 'stats.view'
WHERE r.slug IN ('super-admin', 'admin', 'tenant-owner', 'manager')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);
