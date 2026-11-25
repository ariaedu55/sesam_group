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
