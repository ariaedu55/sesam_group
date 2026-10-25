-- SaaS en libre-service : inscription depuis le site avec essai gratuit, réabonnement depuis l'espace commerce,
-- devis réservé aux sites web (projet détaillé).

ALTER TABLE tenants
    ADD COLUMN phone VARCHAR(40) NULL AFTER status,
    ADD COLUMN signup_source VARCHAR(20) NOT NULL DEFAULT 'console' AFTER phone;

ALTER TABLE users
    ADD COLUMN phone VARCHAR(40) NULL AFTER email;

-- Paiement d'abonnement : saisi par SESAM (console) ou payé en ligne par le commerce (simulation NabooPay).
ALTER TABLE tenant_subscription_payments
    ADD COLUMN channel VARCHAR(20) NOT NULL DEFAULT 'console' AFTER method;

-- Demande de devis pour un site web : type de site, pages, nom de domaine, budget, délai, exemples.
ALTER TABLE contact_requests
    ADD COLUMN business_name VARCHAR(120) NULL AFTER full_name,
    ADD COLUMN site_type VARCHAR(30) NULL AFTER activity,
    ADD COLUMN site_state VARCHAR(20) NULL AFTER site_type,
    ADD COLUMN pages VARCHAR(255) NULL AFTER site_state,
    ADD COLUMN features VARCHAR(255) NULL AFTER pages,
    ADD COLUMN has_domain VARCHAR(20) NULL AFTER features,
    ADD COLUMN budget VARCHAR(30) NULL AFTER has_domain,
    ADD COLUMN deadline VARCHAR(30) NULL AFTER budget,
    ADD COLUMN inspiration VARCHAR(500) NULL AFTER deadline;

-- Le responsable d'un commerce règle lui-même son abonnement depuis son espace.
INSERT IGNORE INTO permissions (slug, name) VALUES ('subscription.pay', 'Régler l''abonnement du commerce');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug = 'subscription.pay' WHERE r.slug = 'tenant-owner';
