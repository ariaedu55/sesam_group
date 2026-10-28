/*
 * Page « Commencer » : récapitulatif de l'offre qui suit le service choisi, et trois popups dédiés.
 *  - guided : inscription guidée dans le popup (accueil, outil, vous, connexion, récapitulatif) ;
 *  - wait   : le visiteur s'apprête à quitter la page (souris vers le haut, ou longue inactivité sur téléphone) ;
 *  - help   : le visiteur reste bloqué longtemps sur une étape du formulaire.
 * Chaque popup automatique n'apparaît qu'une fois par visite ; les boutons « Inscription guidée » l'ouvrent à tout moment.
 */
(function () {
    'use strict';

    var page = document.querySelector('.page-commencer');
    if (!page) {
        return;
    }
    var trial = page.classList.contains('cm-trial');
    var mainForm = document.querySelector('[data-cm-form]');
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var hasServerErrors = !!document.querySelector('.cm-form .field-error');
    var googlePending = mainForm && mainForm.hasAttribute('data-google-pending');

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

    function describe(input) {
        var monthly = parseInt(input.getAttribute('data-cm-monthly') || '0', 10);
        var setup = parseInt(input.getAttribute('data-cm-setup') || '0', 10);
        return {
            label: input.getAttribute('data-cm-label') || '',
            monthly: monthly,
            setup: setup,
            today: trial ? '0 FCFA · 1 mois offert' : (monthly > 0 ? money(monthly + setup) : 'Selon le tarif'),
            after: monthly > 0 ? money(monthly) + ' / mois, sans engagement' : 'Tarif sur demande'
        };
    }

    function updateOffer(input) {
        if (!offer || !input) {
            return;
        }
        var info = describe(input);
        var set = function (selector, text) {
            var el = offer.querySelector(selector);
            if (el) {
                el.textContent = text;
            }
        };
        set('[data-cm-offer-title]', info.label);
        set('[data-cm-offer-today]', trial ? '0 FCFA' : info.today);
        set('[data-cm-offer-after]', info.after);
        offer.classList.remove('is-pulse');
        void offer.offsetWidth;
        offer.classList.add('is-pulse');
    }

    if (mainForm) {
        mainForm.addEventListener('change', function (event) {
            if (event.target && event.target.name === 'sector') {
                updateOffer(event.target);
            }
        });
        var preset = mainForm.querySelector('input[name="sector"]:checked');
        if (preset) {
            updateOffer(preset);
        }
    }

    // Boutons « Commencer » de la bannière : défilement doux vers le formulaire.
    document.querySelectorAll('[data-cm-scroll]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var target = document.getElementById('inscription');
            if (!target) {
                return;
            }
            event.preventDefault();
            target.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
        });
    });

    // ------------------------------------------------------------ Popups

    var modals = {};
    document.querySelectorAll('[data-cm-modal]').forEach(function (el) {
        modals[el.getAttribute('data-cm-modal')] = el;
    });
    var openName = null;
    var lastFocus = null;

    function open(name) {
        var modal = modals[name];
        if (!modal || openName === name) {
            return;
        }
        if (openName) {
            close(true);
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
                var focus = modal.querySelector('[data-g-step]:not([hidden]) input, [data-g-step]:not([hidden]) .wl-btn, .wl-body .wl-btn');
                if (focus) {
                    focus.focus({ preventScroll: true });
                }
            });
        });
        if (name === 'guided') {
            guided.reset();
        }
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
        var opener = target.closest('[data-cm-open]');
        if (opener) {
            event.preventDefault();
            open(opener.getAttribute('data-cm-open'));
            return;
        }
        var switcher = target.closest('[data-cm-switch]');
        if (switcher) {
            event.preventDefault();
            open(switcher.getAttribute('data-cm-switch'));
            return;
        }
        if (target.closest('[data-cm-close]')) {
            event.preventDefault();
            var toForm = target.closest('[data-cm-goto-form]');
            close(false);
            if (toForm) {
                var form = document.getElementById('inscription');
                if (form) {
                    form.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
                }
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!openName) {
            return;
        }
        var modal = modals[openName];
        if (event.key === 'Escape') {
            close(false);
        } else if (event.key === 'Tab') {
            var items = Array.prototype.slice.call(modal.querySelectorAll('a[href], button:not([disabled]), input, [tabindex="0"]')).filter(function (el) {
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

    // ------------------------------------------------------------ Inscription guidée (dans le popup)

    var guided = (function () {
        var form = document.querySelector('[data-cm-guided]');
        if (!form) {
            return { reset: function () {} };
        }
        var order = ['intro', 'sector', 'you', 'login', 'recap'];
        var steps = {};
        form.querySelectorAll('[data-g-step]').forEach(function (el) {
            steps[el.getAttribute('data-g-step')] = el;
        });
        var nav = form.querySelector('[data-g-nav]');
        var navNext = nav ? nav.querySelector('[data-g-next]') : null;
        var navBack = nav ? nav.querySelector('[data-g-back]') : null;
        var submit = form.querySelector('[data-g-submit]');
        var index = 0;

        function field(name) {
            return form.querySelector('[name="' + name + '"]');
        }

        function show(i) {
            index = Math.max(0, Math.min(order.length - 1, i));
            order.forEach(function (name, n) {
                if (steps[name]) {
                    steps[name].hidden = n !== index;
                }
            });
            if (nav) {
                nav.hidden = index === 0;
            }
            if (navNext) {
                navNext.hidden = index === order.length - 1;
            }
            if (submit) {
                submit.hidden = index !== order.length - 1;
            }
            if (order[index] === 'recap') {
                fillRecap();
            }
            var first = steps[order[index]].querySelector('input:not([type=hidden]):not([type=checkbox])');
            if (first && index > 0) {
                window.setTimeout(function () {
                    first.focus({ preventScroll: true });
                }, 60);
            }
        }

        function error(text) {
            var box = steps[order[index]].querySelector('[data-g-error]');
            if (!box) {
                return;
            }
            box.textContent = text || '';
            box.hidden = !text;
        }

        function valid() {
            var name = order[index];
            if (name === 'sector' && !form.querySelector('input[name="sector"]:checked')) {
                return 'Choisissez l\'outil qui correspond à votre métier.';
            }
            if (name === 'you') {
                if (field('full_name').value.trim().length < 2) {
                    return 'Écrivez votre nom.';
                }
                if (!/^\+?[0-9][0-9\s.\-]{7,}$/.test(field('phone').value.trim())) {
                    return 'Numéro WhatsApp invalide. Exemple : 77 123 45 67.';
                }
            }
            if (name === 'login') {
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field('email').value.trim())) {
                    return 'Adresse e-mail invalide.';
                }
                if (field('password').value.length < 12) {
                    return 'Le mot de passe doit faire au moins 12 caractères.';
                }
                if (field('password').value !== field('password_confirm').value) {
                    return 'Les deux mots de passe ne sont pas identiques.';
                }
            }
            if (name === 'recap' && !field('terms').checked) {
                return 'Cochez la case pour accepter la création de votre espace.';
            }
            return '';
        }

        function fillRecap() {
            var sector = form.querySelector('input[name="sector"]:checked');
            var info = sector ? describe(sector) : null;
            var set = function (key, text) {
                var el = form.querySelector('[data-g-recap="' + key + '"]');
                if (el) {
                    el.textContent = text;
                }
            };
            set('sector', info ? info.label : '—');
            var business = field('business_name').value.trim();
            set('you', field('full_name').value.trim() + (business ? ' · ' + business : '') + ' · ' + field('phone').value.trim());
            set('email', field('email').value.trim());
            if (info) {
                set('today', trial ? '0 FCFA · 1 mois offert, puis ' + info.after : info.today + ', puis ' + info.after);
            }
        }

        function next() {
            var problem = valid();
            error(problem);
            if (problem) {
                return;
            }
            show(index + 1);
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
            } else if (target.closest('[data-g-goto]')) {
                event.preventDefault();
                show(order.indexOf(target.closest('[data-g-goto]').getAttribute('data-g-goto')));
            } else if (target.closest('[data-cm-google]')) {
                // Google : on reprend le bouton de la page (même clic = fenêtre Google autorisée), avec l'outil choisi.
                event.preventDefault();
                var chosen = form.querySelector('input[name="sector"]:checked');
                var cfg = document.querySelector('[data-firebase-auth]');
                if (cfg && chosen) {
                    cfg.setAttribute('data-service', chosen.value);
                }
                var google = document.querySelector('.cm-form [data-google-signin]');
                close(false);
                if (google) {
                    google.click();
                }
            }
        });

        // Choisir un outil fait passer directement à l'étape suivante.
        form.addEventListener('change', function (event) {
            if (event.target && event.target.name === 'sector') {
                updateOffer(event.target);
                error('');
                window.setTimeout(function () {
                    if (order[index] === 'sector') {
                        show(index + 1);
                    }
                }, 260);
            }
        });

        form.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target instanceof HTMLInputElement && event.target.type !== 'checkbox' && order[index] !== 'recap') {
                event.preventDefault();
                next();
            }
        });

        form.addEventListener('submit', function (event) {
            if (order[index] !== 'recap') {
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
            if (submit) {
                submit.disabled = true;
                submit.textContent = 'Création de votre espace…';
            }
        });

        return {
            reset: function () {
                // On rouvre là où le visiteur s'était arrêté ; l'accueil seulement la première fois.
                if (index === 0 || !steps[order[index]]) {
                    show(0);
                }
            }
        };
    }());

    // ------------------------------------------------------------ Déclencheurs automatiques

    var interacted = false;
    if (mainForm) {
        ['focusin', 'input', 'change'].forEach(function (type) {
            mainForm.addEventListener(type, function () {
                interacted = true;
                lastProgress = Date.now();
            });
        });
    }
    var lastProgress = Date.now();
    var startedAt = Date.now();

    // 1. Accueil guidé : 5 s après l'arrivée, si le visiteur n'a pas encore commencé le formulaire.
    if (!hasServerErrors && !googlePending) {
        window.setTimeout(function () {
            if (!interacted && !openName && once('guided-auto')) {
                open('guided');
            }
        }, 5000);
    }

    // 2. « Attendez ! » : la souris file vers le haut de la fenêtre (ordinateur), après au moins 8 s sur la page.
    document.addEventListener('mouseout', function (event) {
        if (event.relatedTarget === null && event.clientY <= 0 && Date.now() - startedAt > 8000 && !openName && once('wait')) {
            open('wait');
        }
    });

    // 3. Aide : le visiteur a commencé le formulaire mais n'avance plus depuis 45 s.
    window.setInterval(function () {
        if (interacted && !openName && Date.now() - lastProgress > 45000 && once('help')) {
            open('help');
        }
    }, 5000);

    // Sur téléphone (pas de souris) : rappel après 60 s sans avoir commencé.
    if (window.matchMedia && window.matchMedia('(hover: none)').matches) {
        window.setTimeout(function () {
            if (!interacted && !openName && once('wait')) {
                open('wait');
            }
        }, 60000);
    }
}());
