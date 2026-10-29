/* Page Connexion : « Continuer avec le téléphone / l'e-mail » ouvre le formulaire dans une fenêtre,
   avec seulement le champ utile (numéro avec indicatif, ou e-mail) et le mot de passe. */
(function () {
    'use strict';

    var modal = document.querySelector('[data-login-modal]');
    if (!modal) {
        return;
    }
    // La carte de connexion est animée (transform) : la fenêtre est posée à la racine pour passer au-dessus.
    document.body.appendChild(modal);
    var form = modal.querySelector('[data-login-form]');
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var lastFocus = null;

    function setMode(mode) {
        modal.setAttribute('data-login-mode', mode);
        form.querySelector('[data-login-mode-input]').value = mode;
        modal.querySelectorAll('[data-only]').forEach(function (el) {
            var on = el.getAttribute('data-only') === mode;
            el.hidden = !on;
            el.querySelectorAll('input').forEach(function (input) {
                input.disabled = !on;
                input.required = on;
            });
        });
    }

    function focusFirst() {
        var field = modal.querySelector('[data-only]:not([hidden]) input');
        if (field) {
            field.focus({ preventScroll: true });
        }
    }

    function open(mode) {
        setMode(mode);
        lastFocus = document.activeElement;
        modal.hidden = false;
        document.documentElement.classList.add('lg-lock');
        window.requestAnimationFrame(function () {
            modal.classList.add('is-open');
            window.setTimeout(focusFirst, reduced ? 0 : 120);
        });
    }

    function close() {
        modal.classList.remove('is-open');
        document.documentElement.classList.remove('lg-lock');
        window.setTimeout(function () {
            modal.hidden = true;
        }, reduced ? 0 : 200);
        if (lastFocus && lastFocus.focus) {
            lastFocus.focus({ preventScroll: true });
        }
    }

    document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) {
            return;
        }
        var opener = target.closest('[data-login-open]');
        if (opener) {
            event.preventDefault();
            open(opener.getAttribute('data-login-open'));
            return;
        }
        var switcher = target.closest('[data-login-switch]');
        if (switcher) {
            event.preventDefault();
            setMode(switcher.getAttribute('data-login-switch'));
            focusFirst();
            return;
        }
        if (target.closest('[data-login-close]')) {
            event.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden && !document.querySelector('.iti__dropdown-content:not(.iti__hide)')) {
            close();
        }
    });

    setMode(modal.getAttribute('data-login-mode') || 'phone');
    if (modal.hasAttribute('data-login-autoopen')) {
        open(modal.getAttribute('data-login-mode') || 'phone');
    }
}());
