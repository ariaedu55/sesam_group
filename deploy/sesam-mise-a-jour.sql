-- SESAM — mise à jour de la base de données (25/11/2026)
-- À exécuter UNE SEULE FOIS sur la base de production (phpMyAdmin > base SESAM > Importer, ou mysql NOM_BASE < ce fichier).
-- Faites une sauvegarde (Exporter) avant.
-- Contenu : comptes à plusieurs activités (user_tenant_access) + tarifs multi-devises (pays et devise des commerces).

-- Un même compte (e-mail ou téléphone) peut ouvrir plusieurs activités SESAM (Immo, Réservation, Boutique).
-- users.tenant_id reste l'activité principale du compte ; cette table liste les activités supplémentaires.
CREATE TABLE IF NOT EXISTS user_tenant_access (
    user_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, tenant_id),
    KEY idx_user_tenant_access_tenant (tenant_id),
    CONSTRAINT fk_user_tenant_access_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_tenant_access_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Abonnements en plusieurs devises : FCFA (zone franc CFA), dollar américain (États-Unis, Canada), euro (Europe).
-- Le pays du commerce décide de sa devise ; chaque module a un tarif par devise.

-- Tarifs : une ligne par module et par devise. Les tarifs existants sont en FCFA.
ALTER TABLE saas_prices ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'XOF' AFTER sector;
ALTER TABLE saas_prices DROP PRIMARY KEY, ADD PRIMARY KEY (sector, currency);

-- Pays et devise du commerce. Les commerces existants sont au Sénégal, en FCFA.
ALTER TABLE tenants ADD COLUMN country CHAR(2) NULL AFTER phone;
ALTER TABLE tenants ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'XOF' AFTER country;
UPDATE tenants SET country = 'SN' WHERE country IS NULL;

-- Encaissements : « amount » reste l'équivalent en FCFA (revenus de la console) ;
-- currency + amount_paid gardent le montant réellement payé dans la devise du commerce.
ALTER TABLE tenant_subscription_payments ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'XOF' AFTER amount;
ALTER TABLE tenant_subscription_payments ADD COLUMN amount_paid INT UNSIGNED NULL AFTER currency;
