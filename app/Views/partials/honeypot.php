<?php

declare(strict_types=1);

/*
 * Pièges anti-robots à placer dans chaque formulaire public (voir Sesam\Core\Security\Honeypot) :
 * un champ invisible que seuls les robots remplissent, et l'heure d'affichage signée.
 */

echo \Sesam\Core\Security\Honeypot::current()?->fields() ?? '';
