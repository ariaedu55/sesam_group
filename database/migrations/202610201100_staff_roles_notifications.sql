-- Équipe SESAM : le support, qui n'avait aucun droit, voit les commerces et leurs abonnements en lecture
-- (il reçoit ainsi les inscriptions, fins d'essai et essais perdus à rappeler).
-- Le commercial reste sans accès aux commerces (règle voulue) : il reçoit le suivi de l'agence.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON r.slug = 'support' AND p.slug IN ('tenants.view', 'subscriptions.view')
WHERE NOT EXISTS (
    SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
);
