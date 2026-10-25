-- Rappels clients rattachés à leur objet (ex. « rdv-42 ») : annulés ou remplacés quand le rendez-vous change.
ALTER TABLE message_reminders
    ADD COLUMN reference VARCHAR(60) NULL AFTER template_slug,
    ADD KEY idx_message_reminders_reference (tenant_id, reference, status);
