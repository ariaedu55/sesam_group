/*
 * Popups SESAM (bienvenue après la connexion, et popups du site public) : ouverture animée, visite guidée
 * (Suivant / Passer / glisser), chiffres qui défilent, fermeture par le bouton, la croix, le fond ou Échap.
 * Le focus reste dans le popup.
 * Options (attributs de [data-welcome]) :
 *  - data-delay="4500"      : ouverture après ce délai (ms)…
 *  - data-scroll="0.4"      : …ou dès que le visiteur a parcouru 40 % de la page ;
 *  - data-once="cle" data-once-days="7" : une seule fois par période sur cet appareil ;
 *  - data-once-storage="session" : une seule fois par visite (jusqu'à la fermeture de l'onglet).
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-welcome]');
    if (!root) {
        return;
    }
    var card = root.querySelector('.wl-card');
    var slides = Array.prototype.slice.call(root.querySelectorAll('[data-welcome-slide]'));
    var dots = Array.prototype.slice.call(root.querySelectorAll('.wl-pager i'));
    var next = root.querySelector('[data-welcome-next]');
    var skip = root.querySelector('[data-welcome-skip]');
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var previousFocus = document.activeElement;
    var index = 0;
    var closed = false;
    var opened = false;
    var onceKey = root.getAttribute('data-once');
    var onceDays = parseInt(root.getAttribute('data-once-days') || '7', 10);
    var perVisit = root.getAttribute('data-once-storage') === 'session';

    function store() {
        return perVisit ? window.sessionStorage : window.localStorage;
    }

    function seen() {
        if (!onceKey) {
            return false;
        }
        try {
            // Un visiteur qui a fermé un popup n'en revoit plus aucun pendant sa visite.
            if (perVisit && store().getItem('sesam-popup-ferme')) {
                return true;
            }
            var at = parseInt(store().getItem('sesam-popup-' + onceKey) || '0', 10);
            return at > 0 && (perVisit || Date.now() - at < onceDays * 86400000);
        } catch (e) {
            return false;
        }
    }

    function remember() {
        if (!onceKey) {
            return;
        }
        try {
            store().setItem('sesam-popup-' + onceKey, String(Date.now()));
        } catch (e) {
            /* stockage indisponible : le popup pourra revenir, sans gravité */
        }
    }

    if (seen()) {
        root.remove();
        return;
    }

    function focusables() {
        return Array.prototype.slice.call(card.querySelectorAll('a[href], button:not([disabled])')).filter(function (el) {
            return el.offsetParent !== null;
        });
    }

    function open() {
        if (opened || closed) {
            return;
        }
        opened = true;
        remember();
        root.hidden = false;
        document.documentElement.classList.add('wl-lock');
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                root.classList.add('is-open');
                countUp();
                var main = root.querySelector('[data-welcome-main]') || next;
                if (main) {
                    main.focus({ preventScroll: true });
                }
            });
        });
    }

    function close(event) {
        if (event && event.preventDefault && event.currentTarget && event.currentTarget.tagName === 'BUTTON') {
            event.preventDefault();
        }
        if (closed) {
            return;
        }
        closed = true;
        if (perVisit) {
            try {
                store().setItem('sesam-popup-ferme', '1');
            } catch (e) {
                /* stockage indisponible : sans gravité */
            }
        }
        // Fermeture immédiate pour les clics : le popup ne capte plus rien pendant son animation de sortie.
        root.style.pointerEvents = 'none';
        root.classList.add('is-closing');
        root.classList.remove('is-open');
        document.documentElement.classList.remove('wl-lock');
        window.setTimeout(function () {
            root.hidden = true;
            if (root.parentNode) {
                root.parentNode.removeChild(root);
            }
            if (previousFocus && previousFocus.focus) {
                previousFocus.focus({ preventScroll: true });
            }
        }, reduced ? 0 : 280);
    }

    // Les chiffres du jour défilent de 0 à leur valeur.
    function countUp() {
        root.querySelectorAll('[data-count]').forEach(function (el) {
            var target = parseInt(el.getAttribute('data-count') || '0', 10);
            if (reduced || target <= 0) {
                el.textContent = String(target);
                return;
            }
            var start = null;
            var duration = Math.min(1200, 400 + target * 60);
            el.textContent = '0';
            window.setTimeout(function () {
                function step(time) {
                    if (start === null) {
                        start = time;
                    }
                    var progress = Math.min(1, (time - start) / duration);
                    var eased = 1 - Math.pow(1 - progress, 3);
                    el.textContent = String(Math.round(target * eased));
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    }
                }
                window.requestAnimationFrame(step);
            }, 350);
        });
    }

    // Visite guidée (première connexion)
    function show(i) {
        if (!slides.length) {
            return;
        }
        index = Math.max(0, Math.min(slides.length - 1, i));
        slides.forEach(function (slide, n) {
            slide.hidden = n !== index;
        });
        dots.forEach(function (dot, n) {
            dot.classList.toggle('is-on', n === index);
        });
        var last = index === slides.length - 1;
        if (next) {
            next.textContent = last ? (next.getAttribute('data-last-label') || 'Commencer') : 'Suivant';
        }
        if (skip) {
            skip.hidden = last;
        }
    }

    if (next) {
        next.addEventListener('click', function () {
            if (index >= slides.length - 1) {
                close();
            } else {
                show(index + 1);
            }
        });
    }
    if (skip) {
        skip.addEventListener('click', close);
    }
    // Croix, « Non merci », « C'est parti », fond : un seul écouteur, qui marche aussi sur l'icône de la croix.
    root.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target.closest('[data-welcome-close]') : null;
        if (target && root.contains(target)) {
            if (target.tagName === 'BUTTON') {
                event.preventDefault();
            }
            close();
        }
    });

    // Glisser à gauche / à droite sur téléphone pour changer d'écran.
    var touchX = null;
    card.addEventListener('touchstart', function (event) {
        touchX = event.touches[0].clientX;
    }, { passive: true });
    card.addEventListener('touchend', function (event) {
        if (touchX === null || !slides.length) {
            return;
        }
        var delta = event.changedTouches[0].clientX - touchX;
        touchX = null;
        if (Math.abs(delta) > 50) {
            show(index + (delta < 0 ? 1 : -1));
        }
    });

    document.addEventListener('keydown', function (event) {
        // Tant que le popup n'est pas ouvert, le clavier reste à la page.
        if (closed || !opened) {
            return;
        }
        if (event.key === 'Escape') {
            close();
        } else if (slides.length && event.key === 'ArrowRight') {
            show(index + 1);
        } else if (slides.length && event.key === 'ArrowLeft') {
            show(index - 1);
        } else if (event.key === 'Tab') {
            var items = focusables();
            if (!items.length) {
                return;
            }
            var first = items[0];
            var lastItem = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                lastItem.focus();
            } else if (!event.shiftKey && document.activeElement === lastItem) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    show(0);
    // La page s'affiche d'abord, puis le popup arrive (après le délai, ou plus tôt si le visiteur fait défiler).
    var delay = parseInt(root.getAttribute('data-delay') || '350', 10);
    window.setTimeout(open, reduced && delay < 1000 ? 0 : delay);
    var scroll = parseFloat(root.getAttribute('data-scroll') || '0');
    if (scroll > 0) {
        var onScroll = function () {
            var max = document.documentElement.scrollHeight - window.innerHeight;
            if (max > 0 && window.pageYOffset / max >= scroll) {
                window.removeEventListener('scroll', onScroll);
                open();
            }
        };
        window.addEventListener('scroll', onScroll, { passive: true });
    }
}());
