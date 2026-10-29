/*
 * Champs téléphone de tout SESAM : sélecteur d'indicatif pays (intl-tel-input 24.8.2, drapeaux et noms en français).
 *  - le pays est deviné sans appel extérieur (fuseau horaire du téléphone, puis langue), Sénégal par défaut ;
 *  - on peut choisir un autre indicatif dans la liste (recherche par nom de pays) ;
 *  - à l'envoi du formulaire, le champ contient le numéro complet au format international (+221771234567),
 *    que le serveur ramène à « 221771234567 ».
 * Les formulaires chargés plus tard (fenêtres modales) sont pris en charge automatiquement.
 * API : window.SesamPhone.isValid(input), window.SesamPhone.number(input).
 */
(function () {
    'use strict';

    if (typeof window.intlTelInput !== 'function') {
        return;
    }

    var ZONES = {
        'Africa/Dakar': 'sn', 'Africa/Abidjan': 'ci', 'Africa/Bamako': 'ml', 'Africa/Conakry': 'gn', 'Africa/Nouakchott': 'mr',
        'Africa/Banjul': 'gm', 'Africa/Bissau': 'gw', 'Africa/Ouagadougou': 'bf', 'Africa/Niamey': 'ne', 'Africa/Lome': 'tg',
        'Africa/Porto-Novo': 'bj', 'Africa/Lagos': 'ng', 'Africa/Accra': 'gh', 'Africa/Douala': 'cm', 'Africa/Libreville': 'ga',
        'Africa/Kinshasa': 'cd', 'Africa/Brazzaville': 'cg', 'Africa/Casablanca': 'ma', 'Africa/Algiers': 'dz', 'Africa/Tunis': 'tn',
        'Atlantic/Cape_Verde': 'cv', 'Europe/Paris': 'fr', 'Europe/Brussels': 'be', 'Europe/Zurich': 'ch', 'Europe/Luxembourg': 'lu',
        'Europe/Madrid': 'es', 'Europe/Rome': 'it', 'Europe/Berlin': 'de', 'Europe/London': 'gb', 'Europe/Lisbon': 'pt',
        'America/Montreal': 'ca', 'America/Toronto': 'ca', 'America/New_York': 'us', 'America/Chicago': 'us', 'America/Los_Angeles': 'us'
    };
    var instances = new WeakMap();

    function guessCountry() {
        try {
            var zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (ZONES[zone]) {
                return ZONES[zone];
            }
        } catch (e) {
            /* fuseau indisponible : on passe à la langue */
        }
        var region = (navigator.language || '').split('-')[1];
        return region && region.length === 2 ? region.toLowerCase() : 'sn';
    }

    var defaultCountry = guessCountry();

    function enhance(input) {
        if (instances.has(input) || input.hasAttribute('data-phone-plain') || input.closest('.iti')) {
            return;
        }
        // Valeur déjà enregistrée (« 221771234567 ») : on remet le « + » pour que le bon pays soit reconnu.
        var value = input.value.trim();
        if (/^[1-9]\d{9,14}$/.test(value)) {
            input.value = '+' + value;
        }
        input.removeAttribute('maxlength');
        if (input.getAttribute('placeholder') === '77 123 45 67') {
            input.removeAttribute('placeholder');
        }
        var iti = window.intlTelInput(input, {
            initialCountry: defaultCountry,
            countryOrder: ['sn', 'ci', 'ml', 'gn', 'mr', 'gm', 'bf', 'fr'],
            separateDialCode: true,
            nationalMode: true,
            formatAsYouType: true,
            autoPlaceholder: 'aggressive',
            countrySearch: true,
            containerClass: 'sesam-tel',
            // Liste des pays posée sur la page : jamais coupée par une fenêtre modale qui défile.
            dropdownContainer: document.body,
            i18n: window.SesamItiFr || {}
        });
        instances.set(input, iti);

        var form = input.form;
        if (form && !form.hasAttribute('data-phone-bound')) {
            form.setAttribute('data-phone-bound', '1');
            // Avant l'envoi : chaque champ téléphone du formulaire reçoit le numéro international complet.
            form.addEventListener('submit', function () {
                form.querySelectorAll('input[type="tel"]').forEach(function (field) {
                    var instance = instances.get(field);
                    if (instance && field.value.trim() !== '') {
                        field.value = instance.getNumber() || field.value;
                    }
                });
            }, true);
        }
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) {
            return;
        }
        if (root.matches && root.matches('input[type="tel"]')) {
            enhance(root);
        }
        root.querySelectorAll('input[type="tel"]').forEach(enhance);
    }

    window.SesamPhone = {
        isValid: function (input) {
            var iti = instances.get(input);
            return iti ? iti.isValidNumber() === true : /^\+?[0-9][0-9\s.\-]{7,}$/.test(input.value.trim());
        },
        number: function (input) {
            var iti = instances.get(input);
            return iti ? (iti.getNumber() || input.value.trim()) : input.value.trim();
        },
        enhance: scan
    };

    scan(document);
    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(scan);
        });
    }).observe(document.documentElement, { childList: true, subtree: true });
}());
