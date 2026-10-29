/*
 * Page « Commencer » : le visiteur choisit comment s'inscrire (téléphone, e-mail ou Google),
 * puis le formulaire s'ouvre dans une fenêtre et n'affiche que les champs utiles à ce mode :
 *  - téléphone : outil → nom, commerce, numéro → mot de passe ;
 *  - e-mail    : outil → nom, commerce, numéro → e-mail et mot de passe ;
 *  - Google    : outil → nom, commerce, numéro (et c'est tout).
 * Deux popups d'accompagnement : « wait » (le visiteur s'en va) et « help » (il hésite), une fois par visite.
 */
(function () {
    'use strict';

    var page = document.querySelector('.page-commencer');
    if (!page) {
        return;
    }
    var trial = page.classList.contains('cm-trial');
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function money(value) {
        return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
    }

    function once(key) {
        try {
            if (window.sessionStorage.getItem('cm-' + key)) {
                return false;
            }
            window.sessionStorage.setItem('cm-' + key, '1');
        } catch (e) {
            /* stockage indisponible : on affiche quand même, une fois par chargement */
        }
        return true;
    }

    // ------------------------------------------------------------ Récapitulatif de l'offre

    var offer = document.querySelector('[data-cm-offer]');

    function updateOffer(input) {
        if (!offer || !input) {
            return;
        }
        var monthly = parseInt(input.getAttribute('data-cm-monthly') || '0', 10);
        var setup = parseInt(input.getAttribute('data-cm-setup') || '0', 10);
        var set = function (selector, text) {
            var el = offer.querySelector(selector);
            if (el) {
                el.textContent = text;
            }
        };
        set('[data-cm-offer-title]', input.getAttribute('data-cm-label') || '');
        set('[data-cm-offer-today]', trial ? '0 FCFA' : (monthly > 0 ? money(monthly + setup) : 'Selon le tarif'));
        set('[data-cm-offer-after]', monthly > 0 ? money(monthly) + ' / mois, sans engagement' : 'Tarif sur demande');
        offer.classList.remove('is-pulse');
        void offer.offsetWidth;
        offer.classList.add('is-pulse');
    }

    // Boutons de la bannière : défilement doux vers le choix du mode d'inscription.
    document.querySelectorAll('[data-cm-scroll]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var target = document.getElementById('inscription-choix');
            if (!target) {
                return;
            }
            event.preventDefault();
            target.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'center' });
        });
    });

    // ------------------------------------------------------------ Fenêtres

    var modals = {};
    document.querySelectorAll('[data-cm-modal]').forEach(function (el) {
        modals[el.getAttribute('data-cm-modal')] = el;
    });
    var openName = null;
    var lastFocus = null;
    var started = false;

    function open(name, onOpen) {
        var modal = modals[name];
        if (!modal) {
            return;
        }
        if (openName && openName !== name) {
            close(true);
        }
        if (onOpen) {
            onOpen();
        }
        if (openName === name) {
            return;
        }
        openName = name;
        lastFocus = document.activeElement;
        modal.hidden = false;
        modal.classList.remove('is-closing');
        modal.style.pointerEvents = '';
        document.documentElement.classList.add('wl-lock');
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                modal.classList.add('is-open');
                var focus = modal.querySelector('[data-g-step]:not([hidden]) input:not([type=hidden]), .wl-body .wl-btn');
                if (focus) {
                    focus.focus({ preventScroll: true });
                }
            });
        });
    }

    function close(silent) {
        var modal = openName ? modals[openName] : null;
        if (!modal) {
            return;
        }
        openName = null;
        modal.style.pointerEvents = 'none';
        modal.classList.remove('is-open');
        modal.classList.add('is-closing');
        if (!silent) {
            document.documentElement.classList.remove('wl-lock');
        }
        window.setTimeout(function () {
            modal.hidden = true;
            modal.classList.remove('is-closing');
            if (!openName) {
                document.documentElement.classList.remove('wl-lock');
            }
        }, reduced ? 0 : 260);
        if (!silent && lastFocus && lastFocus.focus) {
            lastFocus.focus({ preventScroll: true });
        }
    }

    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) {
            return;
        }
        var chooser = target.closest('[data-cm-signup]');
        if (chooser) {
            event.preventDefault();
            started = true;
            var mode = chooser.getAttribute('data-cm-signup');
            open('signup', function () {
                signup.setMode(mode);
            });
            return;
        }
        var opener = target.closest('[data-cm-open]');
        if (opener) {
            event.preventDefault();
            open(opener.getAttribute('data-cm-open'));
            return;
        }
        if (target.closest('[data-cm-close]')) {
            event.preventDefault();
            close(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!openName) {
            return;
        }
        var modal = modals[openName];
        // La liste des pays du champ téléphone gère elle-même Échap.
        if (event.key === 'Escape' && !document.querySelector('.iti__dropdown-content:not(.iti__hide)')) {
            close(false);
        } else if (event.key === 'Tab') {
            var items = Array.prototype.slice.call(modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), [tabindex="0"]')).filter(function (el) {
                return el.offsetParent !== null;
            });
            if (!items.length) {
                return;
            }
            if (event.shiftKey && document.activeElement === items[0]) {
                event.preventDefault();
                items[items.length - 1].focus();
            } else if (!event.shiftKey && document.activeElement === items[items.length - 1]) {
                event.preventDefault();
                items[0].focus();
            }
        }
    });

    // ------------------------------------------------------------ Formulaire d'inscription

    var signup = (function () {
        var modal = modals.signup;
        var form = modal ? modal.querySelector('[data-cm-signup-form]') : null;
        if (!form) {
            return { setMode: function () {} };
        }
        var mode = modal.getAttribute('data-signup-mode') || 'phone';
        var steps = {};
        form.querySelectorAll('[data-g-step]').forEach(function (el) {
            steps[el.getAttribute('data-g-step')] = el;
        });
        var navNext = form.querySelector('[data-g-next]');
        var navBack = form.querySelector('[data-g-back]');
        var submit = form.querySelector('[data-g-submit]');
        var progress = form.querySelector('[data-signup-progress]');
        var bar = form.querySelector('[data-signup-bar]');
        var index = 0;

        function order() {
            return mode === 'google' ? ['sector', 'you'] : ['sector', 'you', 'login'];
        }

        function field(name) {
            var found = null;
            form.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
                if (!el.disabled) {
                    found = el;
                }
            });
            return found;
        }

        // N'affiche (et n'envoie) que ce qui sert au mode choisi.
        function applyMode() {
            modal.setAttribute('data-signup-mode', mode);
            form.querySelector('[data-signup-mode-input]').value = mode;
            form.querySelectorAll('[data-only]').forEach(function (el) {
                var on = el.getAttribute('data-only').split(' ').indexOf(mode) !== -1;
                el.hidden = !on;
                el.querySelectorAll('input').forEach(function (input) {
                    input.disabled = !on;
                });
            });
            var login = steps.login;
            if (login) {
                login.querySelectorAll('input').forEach(function (input) {
                    if (!input.closest('[data-only]')) {
                        input.disabled = mode === 'google';
                    }
                });
            }
        }

        function show(i) {
            var list = order();
            index = Math.max(0, Math.min(list.length - 1, i));
            Object.keys(steps).forEach(function (name) {
                steps[name].hidden = name !== list[index];
            });
            var last = index === list.length - 1;
            navBack.hidden = index === 0;
            navNext.hidden = last;
            submit.hidden = !last;
            if (progress) {
                progress.textContent = 'Étape ' + (index + 1) + ' sur ' + list.length;
            }
            if (bar) {
                bar.style.setProperty('--p', Math.round((index + 1) * 100 / list.length) + '%');
            }
            var first = steps[list[index]].querySelector('input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([disabled])');
            if (first && index > 0) {
                window.setTimeout(function () {
                    first.focus({ preventScroll: true });
                }, 60);
            }
        }

        function error(text) {
            var box = steps[order()[index]].querySelector('[data-g-error]');
            if (box) {
                box.textContent = text || '';
                box.hidden = !text;
            }
        }

        function valid() {
            var name = order()[index];
            var last = index === order().length - 1;
            if (name === 'sector' && !form.querySelector('input[name="sector"]:checked')) {
                return 'Choisissez l\'outil qui correspond à votre métier.';
            }
            if (name === 'you') {
                if (field('full_name').value.trim().length < 2) {
                    return 'Écrivez votre nom.';
                }
                if (field('business_name').value.trim().length < 2) {
                    return 'Écrivez le nom de votre commerce.';
                }
                var phone = field('phone');
                if (window.SesamPhone ? !window.SesamPhone.isValid(phone) : phone.value.trim().length < 8) {
                    return 'Numéro de téléphone invalide : vérifiez l\'indicatif du pays et le numéro.';
                }
            }
            if (name === 'login') {
                if (mode === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field('email').value.trim())) {
                    return 'Adresse e-mail invalide.';
                }
                if (field('password').value.length < 12) {
                    return 'Le mot de passe doit faire au moins 12 caractères.';
                }
                if (field('password').value !== field('password_confirm').value) {
                    return 'Les deux mots de passe ne sont pas identiques.';
                }
            }
            if (last && !field('terms').checked) {
                return 'Cochez la case pour accepter la création de votre espace.';
            }
            return '';
        }

        function next() {
            var problem = valid();
            error(problem);
            if (!problem) {
                show(index + 1);
            }
        }

        form.addEventListener('click', function (event) {
            var target = event.target instanceof Element ? event.target : null;
            if (!target) {
                return;
            }
            if (target.closest('[data-g-next]')) {
                event.preventDefault();
                next();
            } else if (target.closest('[data-g-back]')) {
                event.preventDefault();
                error('');
                show(index - 1);
            } else if (target.closest('[data-signup-switch]')) {
                event.preventDefault();
                mode = target.closest('[data-signup-switch]').getAttribute('data-signup-switch');
                applyMode();
                show(Math.min(index, order().length - 1));
            }
        });

        // Choisir un outil fait passer directement à l'étape suivante.
        form.addEventListener('change', function (event) {
            var target = event.target;
            if (target && target.name === 'sector') {
                updateOffer(target);
                error('');
                window.setTimeout(function () {
                    if (order()[index] === 'sector') {
                        show(index + 1);
                    }
                }, 240);
            }
            if (target && target.hasAttribute('data-signup-showpw')) {
                form.querySelectorAll('input[name="password"], input[name="password_confirm"]').forEach(function (input) {
                    input.type = target.checked ? 'text' : 'password';
                });
            }
        });

        form.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target instanceof HTMLInputElement && event.target.type !== 'checkbox' && index < order().length - 1) {
                event.preventDefault();
                next();
            }
        });

        form.addEventListener('submit', function (event) {
            if (index < order().length - 1) {
                event.preventDefault();
                next();
                return;
            }
            var problem = valid();
            if (problem) {
                event.preventDefault();
                error(problem);
                return;
            }
            submit.disabled = true;
            submit.textContent = 'Création de votre espace…';
        });

        applyMode();
        var preset = form.querySelector('input[name="sector"]:checked');
        if (preset) {
            updateOffer(preset);
        }
        // Retour du serveur avec une erreur : on rouvre l'étape concernée.
        var firstError = form.querySelector('.cm-g-field-error');
        var errorStep = firstError ? firstError.closest('[data-g-step]') : null;
        show(errorStep ? Math.max(0, order().indexOf(errorStep.getAttribute('data-g-step'))) : (mode === 'google' && preset ? 1 : 0));

        return {
            setMode: function (value) {
                if (value === mode) {
                    return;
                }
                mode = value;
                applyMode();
                show(Math.min(index, order().length - 1));
            }
        };
    }());

    if (modals.signup && modals.signup.hasAttribute('data-cm-autoopen')) {
        started = true;
        open('signup');
    }

    // ------------------------------------------------------------ Popups d'accompagnement

    var startedAt = Date.now();

    // « Attendez ! » : la souris file vers le haut de la fenêtre (ordinateur), après au moins 8 s sur la page.
    document.addEventListener('mouseout', function (event) {
        if (event.relatedTarget === null && event.clientY <= 0 && Date.now() - startedAt > 8000 && !openName && once('wait')) {
            open('wait');
        }
    });

    // Aide : 45 s sur la page sans avoir commencé l'inscription.
    window.setTimeout(function () {
        if (!started && !openName && once('help')) {
            open('help');
        }
    }, 45000);

    // Sur téléphone (pas de souris) : rappel après 60 s sans avoir commencé.
    if (window.matchMedia && window.matchMedia('(hover: none)').matches) {
        window.setTimeout(function () {
            if (!started && !openName && once('wait')) {
                open('wait');
            }
        }, 60000);
    }
}());
