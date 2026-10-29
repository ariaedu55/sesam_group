-- Inscription et connexion par numéro de téléphone : l'e-mail devient facultatif (plusieurs NULL autorisés
-- par l'index unique), et le numéro, enregistré au format international sans « + », est indexé pour la connexion.
ALTER TABLE users
    MODIFY email VARCHAR(190) NULL,
    ADD KEY idx_users_phone (phone);
