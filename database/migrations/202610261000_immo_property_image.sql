-- Photo facultative d'un bien Immo (immeuble, villa…). Stockée via ImageUploadService
-- sous storage/uploads/immo/{tenant_id}/{32hex}.webp. NULL = pas de photo.
ALTER TABLE loyer_properties
    ADD COLUMN image_path VARCHAR(80) NULL AFTER notes;
