-- Modèles de messages du module Réservation (communs à tous les salons).
INSERT INTO message_templates (tenant_id, slug, channel, name, subject, body, created_at, updated_at)
SELECT NULL, seed.slug, seed.channel, 'Rappel de rendez-vous', seed.subject, seed.body, UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM (
    SELECT 'rdv_rappel' AS slug, 'whatsapp' AS channel, NULL AS subject,
           'Bonjour {{name}}, petit rappel : votre rendez-vous {{details}} chez {{commerce}}. À bientôt !' AS body
    UNION ALL SELECT 'rdv_rappel', 'email', 'Rappel de votre rendez-vous',
           'Bonjour {{name}}, nous vous rappelons votre rendez-vous {{details}} chez {{commerce}}. À bientôt !'
    UNION ALL SELECT 'rdv_rappel', 'internal', NULL,
           'Rappel envoyé à {{name}} : {{details}}.'
) AS seed
WHERE NOT EXISTS (
    SELECT 1 FROM message_templates t WHERE t.tenant_id IS NULL AND t.slug = seed.slug AND t.channel = seed.channel
);
