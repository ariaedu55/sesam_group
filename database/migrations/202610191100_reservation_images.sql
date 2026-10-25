-- Réservation : photo de chaque prestation (affichée sur la page publique du salon).
ALTER TABLE reservation_services
    ADD COLUMN image_path VARCHAR(255) NULL AFTER description;
