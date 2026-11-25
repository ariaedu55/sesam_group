/* Page Tarifs de la console : onglets de devise et prix des formules recalculés pendant la saisie. */
(function () {
    'use strict';
    // Onglets de devise dans chaque service.
    document.querySelectorAll('.px-tabs').forEach(function (list) {
        var tabs = list.querySelectorAll('[data-px-tab]');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (other) {
                    var selected = other === tab;
                    other.setAttribute('aria-selected', selected ? 'true' : 'false');
                    var panel = document.getElementById(other.getAttribute('data-px-tab'));
                    if (panel) {
                        panel.hidden = !selected;
                    }
                });
            });
        });
    });

    // Prix des formules recalculés pendant la saisie.
    var decimals = {XOF: 0, USD: 2, EUR: 2};
    function parse(value, currency) {
        var clean = String(value).replace(/[\s $€]/g, '').replace(',', '.');
        var number = parseFloat(clean);
        return isNaN(number) ? 0 : Math.round(number * Math.pow(10, decimals[currency] || 0));
    }
    function format(minor, currency) {
        var d = decimals[currency] || 0;
        var text = (minor / Math.pow(10, d)).toFixed(d).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        return text + (currency === 'USD' ? ' $ US' : currency === 'EUR' ? ' €' : ' FCFA');
    }
    function refresh(panel) {
        var currency = panel.getAttribute('data-currency');
        var monthly = parse(panel.querySelector('[data-px-monthly]').value, currency);
        panel.querySelectorAll('[data-px-plan]').forEach(function (cell) {
            var months = parseInt(cell.getAttribute('data-px-plan'), 10);
            var discount = Math.min(90, Math.max(0, parseInt(cell.querySelector('[data-px-discount]').value || '0', 10) || 0));
            var enabled = cell.querySelector('[data-px-enabled]').checked;
            cell.classList.toggle('is-off', !enabled);
            var out = cell.querySelector('[data-px-price]');
            if (monthly <= 0) {
                out.textContent = 'Mensualité à définir';
                return;
            }
            var base = monthly * months;
            var amount = Math.round(base * (100 - discount) / 100);
            out.innerHTML = '';
            var strong = document.createElement('strong');
            strong.textContent = format(amount, currency);
            out.appendChild(strong);
            if (discount > 0) {
                var old = document.createElement('s');
                old.textContent = format(base, currency);
                out.appendChild(document.createTextNode(' '));
                out.appendChild(old);
            }
        });
    }
    document.querySelectorAll('[data-px-panel]').forEach(function (panel) {
        panel.addEventListener('input', function () { refresh(panel); });
        panel.addEventListener('change', function () { refresh(panel); });
    });
})();
