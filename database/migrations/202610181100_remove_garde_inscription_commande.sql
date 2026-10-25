-- Les modules Garde et Inscription sont retirés de la plateforme.
-- Le module Commande est remplacé par le module Boutique (202610181000_create_boutique.sql).

DROP TABLE IF EXISTS garde_movements;
DROP TABLE IF EXISTS garde_batches;
DROP TABLE IF EXISTS garde_products;
DROP TABLE IF EXISTS garde_shifts;
DROP TABLE IF EXISTS garde_staff;

DROP TABLE IF EXISTS inscription_installments;
DROP TABLE IF EXISTS inscription_enrollments;
DROP TABLE IF EXISTS inscription_fee_types;
DROP TABLE IF EXISTS inscription_classes;

DROP TABLE IF EXISTS commande_deliveries;
DROP TABLE IF EXISTS commande_order_measurements;
DROP TABLE IF EXISTS commande_order_items;
DROP TABLE IF EXISTS commande_orders;
DROP TABLE IF EXISTS commande_products;

DELETE rp FROM role_permissions rp
INNER JOIN permissions p ON p.id = rp.permission_id
WHERE p.slug LIKE 'garde.%' OR p.slug LIKE 'inscription.%' OR p.slug LIKE 'commande.%';

DELETE FROM permissions
WHERE slug LIKE 'garde.%' OR slug LIKE 'inscription.%' OR slug LIKE 'commande.%';

-- Les commerces Commande deviennent des boutiques.
UPDATE tenants SET sector = 'boutique', updated_at = UTC_TIMESTAMP() WHERE sector = 'commande';

-- Les commerces Garde ou Inscription n'ont plus de module : ils sont suspendus (rien n'est effacé).
UPDATE tenants SET status = 'suspended', updated_at = UTC_TIMESTAMP() WHERE sector IN ('garde', 'inscription');
