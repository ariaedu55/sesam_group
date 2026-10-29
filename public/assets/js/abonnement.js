/* Page Abonnement : le bouton « Renouveler » ouvre le choix de la formule et du moyen de paiement,
   le récapitulatif suit la formule et le moyen choisis. Sans JavaScript, tout reste visible. */
(function () {
    'use strict';

    var form = document.querySelector('[data-sub-form]');
    if (!form) {
        return;
    }

    // Abonnement en cours : le paiement reste replié tant que le commerçant n'a pas cliqué sur « Renouveler ».
    if (form.hasAttribute('data-sub-collapsed') && window.location.hash !== '#payer') {
        form.classList.add('is-collapsed');
    }

    function open(focus) {
        form.classList.remove('is-collapsed');
        form.classList.add('is-open');
        form.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        if (focus) {
            var checked = form.querySelector('input[name="months"]:checked') || form.querySelector('input[name="months"]');
            if (checked) {
                checked.focus({ preventScroll: true });
            }
        }
    }

    document.querySelectorAll('[data-sub-open]').forEach(function (button) {
        button.setAttribute('aria-controls', form.id);
        button.addEventListener('click', function (event) {
            event.preventDefault();
            open(true);
        });
    });

    function update() {
        var plan = form.querySelector('input[name="months"]:checked');
        var method = form.querySelector('input[name="method"]:checked');
        if (!plan) {
            return;
        }
        var set = function (selector, text) {
            var node = form.querySelector(selector);
            if (node && text) {
                node.textContent = text;
            }
        };
        set('[data-recap-plan]', plan.getAttribute('data-plan-label'));
        set('[data-recap-end]', plan.getAttribute('data-end'));
        set('[data-recap-amount]', plan.getAttribute('data-amount-label'));
        set('[data-recap-button]', 'Payer ' + plan.getAttribute('data-amount-label') + (method ? ' avec ' + method.getAttribute('data-method-label') : ''));
    }

    form.addEventListener('change', update);
    update();
}());
