<?php

declare(strict_types=1);

/*
 * Choix de la langue (français / anglais) : petit sélecteur posé dans la barre du haut.
 * La traduction est faite par GTranslate (partials/gtranslate.php, chargé une fois par page).
 * $langSwitchClass : classe en plus selon l'endroit (site, espace, connexion).
 */

$langSwitchClass = is_string($langSwitchClass ?? null) ? $langSwitchClass : '';

?>
<div class="lang-switch notranslate <?= htmlspecialchars($langSwitchClass, ENT_QUOTES) ?>" translate="no" role="group" aria-label="Langue / Language" data-lang-switch>
    <button type="button" data-lang="fr" aria-pressed="true" title="Français" aria-label="Français"><span class="lang-flag iti__flag iti__fr" aria-hidden="true"></span></button>
    <button type="button" data-lang="en" aria-pressed="false" title="English" aria-label="English"><span class="lang-flag iti__flag iti__gb" aria-hidden="true"></span></button>
</div>
