-- Formules longues par module (3 mois, 6 mois, 1 an) : proposées ou non, avec un pourcentage de réduction chacune.
ALTER TABLE saas_prices
    ADD COLUMN plan_3_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER setup_fee,
    ADD COLUMN plan_3_discount TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER plan_3_enabled,
    ADD COLUMN plan_6_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER plan_3_discount,
    ADD COLUMN plan_6_discount TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER plan_6_enabled,
    ADD COLUMN plan_12_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER plan_6_discount,
    ADD COLUMN plan_12_discount TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER plan_12_enabled;
