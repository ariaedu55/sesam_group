/*
 * Formulaire commerce (console) : la devise et le tarif affichés suivent le pays choisi
 * et les activités cochées (le tarif de chaque devise est défini sur la page Tarifs).
 */
(function () {
    var select = document.querySelector('[data-country-select]');
    var out = document.querySelector('[data-country-currency]');
    var preview = document.querySelector('[data-price-preview]');
    var names = {FCFA: 'Franc CFA', USD: 'Dollar américain', EUR: 'Euro'};
    if (!select) {
        return;
    }
    var prices = {};
    try {
        prices = preview ? JSON.parse(preview.getAttribute('data-prices') || '{}') : {};
    } catch (e) {
        prices = {};
    }

    function sectors() {
        if (preview && preview.getAttribute('data-sector')) {
            return [preview.getAttribute('data-sector')];
        }
        var checked = Array.prototype.map.call(document.querySelectorAll('input[name="sectors[]"]:checked'), function (input) {
            return input.value;
        });
        var single = document.querySelector('select[name="sector"]');
        return checked.length ? checked : (single ? [single.value] : []);
    }

    function refresh() {
        var option = select.options[select.selectedIndex];
        var currency = option ? option.getAttribute('data-currency') : 'FCFA';
        if (out) {
            out.textContent = names[currency] || '';
        }
        if (!preview) {
            return;
        }
        var list = preview.querySelector('[data-price-preview-list]');
        list.innerHTML = '';
        sectors().forEach(function (sector) {
            var row = (prices[currency] || {})[sector];
            if (!row) {
                return;
            }
            var li = document.createElement('li');
            var name = document.createElement('span');
            name.textContent = row.product;
            var price = document.createElement('strong');
            price.textContent = row.label;
            li.appendChild(name);
            li.appendChild(price);
            list.appendChild(li);
        });
        preview.hidden = list.children.length === 0;
    }

    select.addEventListener('change', refresh);
    document.addEventListener('change', function (event) {
        if (event.target && (event.target.name === 'sectors[]' || event.target.name === 'sector')) {
            refresh();
        }
    });
    refresh();
})();
