/* Page publique d'une boutique : panier, recherche, catégories. Sans JavaScript, le formulaire fonctionne tel quel. */
(function () {
    'use strict';

    var form = document.querySelector('[data-shop]');
    if (!form) {
        return;
    }
    document.documentElement.classList.add('js');

    var slug = form.getAttribute('data-shop') || '';
    var storageKey = 'sesam-panier-' + slug;
    var cards = Array.prototype.slice.call(form.querySelectorAll('[data-product]'));
    var checkout = form.querySelector('[data-checkout]');
    var lines = form.querySelector('[data-cart-lines]');
    var totalEl = form.querySelector('[data-cart-total]');
    var bar = form.querySelector('[data-cart-bar]');
    var barCount = form.querySelector('[data-bar-count]');
    var barTotal = form.querySelector('[data-bar-total]');
    var headerCount = document.querySelector('[data-cart-count]');
    var submit = form.querySelector('[data-cart-submit]');

    function money(value) {
        return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
    }

    function input(card) {
        return card.querySelector('input[name^="items["]');
    }

    function clamp(card, value) {
        var max = parseInt(card.getAttribute('data-max') || '99', 10);
        var n = parseInt(value, 10);
        if (isNaN(n) || n < 0) {
            n = 0;
        }
        return Math.min(n, max);
    }

    function save() {
        var data = {};
        cards.forEach(function (card) {
            var field = input(card);
            if (field && parseInt(field.value, 10) > 0) {
                data[card.getAttribute('data-product')] = parseInt(field.value, 10);
            }
        });
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(data));
        } catch (e) {
            /* stockage indisponible : le panier reste en mémoire */
        }
    }

    function restore() {
        var hasServerValues = cards.some(function (card) {
            var field = input(card);
            return field && parseInt(field.value, 10) > 0;
        });
        if (hasServerValues) {
            return;
        }
        var data = {};
        try {
            data = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {};
        } catch (e) {
            data = {};
        }
        cards.forEach(function (card) {
            var field = input(card);
            var id = card.getAttribute('data-product');
            if (field && data[id]) {
                field.value = clamp(card, data[id]);
            }
        });
    }

    function render() {
        var count = 0;
        var total = 0;
        if (lines) {
            lines.innerHTML = '';
        }
        cards.forEach(function (card) {
            var field = input(card);
            var qty = field ? clamp(card, field.value) : 0;
            if (field) {
                field.value = qty;
            }
            card.classList.toggle('is-in-cart', qty > 0);
            if (qty < 1) {
                return;
            }
            var price = parseInt(card.getAttribute('data-price') || '0', 10);
            count += qty;
            total += qty * price;
            if (lines) {
                var li = document.createElement('li');
                var label = document.createElement('span');
                label.textContent = qty + ' × ' + card.getAttribute('data-name');
                var amount = document.createElement('strong');
                amount.textContent = money(qty * price);
                li.appendChild(label);
                li.appendChild(amount);
                lines.appendChild(li);
            }
        });
        if (lines && count === 0) {
            var empty = document.createElement('li');
            empty.className = 'shop-lines-empty';
            empty.textContent = 'Votre panier est vide. Ajoutez un produit pour commander.';
            lines.appendChild(empty);
        }
        if (totalEl) {
            totalEl.textContent = money(total);
        }
        if (bar) {
            bar.hidden = count === 0;
        }
        if (barCount) {
            barCount.textContent = String(count);
        }
        if (barTotal) {
            barTotal.textContent = money(total);
        }
        if (headerCount) {
            headerCount.textContent = String(count);
        }
        if (submit) {
            submit.disabled = count === 0;
        }
        save();
    }

    function open() {
        if (!checkout) {
            return;
        }
        checkout.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        var first = checkout.querySelector('input:not([type=hidden])');
        if (first) {
            window.setTimeout(function () {
                first.focus();
            }, 150);
        }
    }

    function close() {
        if (!checkout) {
            return;
        }
        checkout.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    cards.forEach(function (card) {
        var field = input(card);
        if (!field) {
            return;
        }
        card.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            var step = target.closest('[data-step]');
            if (step) {
                field.value = clamp(card, parseInt(field.value || '0', 10) + parseInt(step.getAttribute('data-step'), 10));
                render();
            }
            if (target.closest('[data-add]')) {
                field.value = clamp(card, 1);
                render();
            }
        });
        field.addEventListener('change', render);
    });

    document.querySelectorAll('[data-cart-open]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            open();
        });
    });
    form.querySelectorAll('[data-cart-close]').forEach(function (button) {
        button.addEventListener('click', close);
    });
    if (checkout) {
        checkout.addEventListener('click', function (event) {
            if (event.target === checkout) {
                close();
            }
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            close();
        }
    });

    form.addEventListener('submit', function () {
        try {
            window.localStorage.removeItem(storageKey);
        } catch (e) {
            /* rien */
        }
    });

    /* Recherche et filtres */
    var search = form.querySelector('[data-shop-search]');
    var chips = Array.prototype.slice.call(form.querySelectorAll('[data-category]')).filter(function (el) {
        return el.tagName === 'BUTTON';
    });
    var noResult = form.querySelector('[data-shop-noresult]');
    var activeCategory = '';

    function filter() {
        var q = search ? search.value.trim().toLowerCase() : '';
        var visible = 0;
        cards.forEach(function (card) {
            var matchText = q === '' || (card.getAttribute('data-name') || '').toLowerCase().indexOf(q) !== -1;
            var matchCat = activeCategory === '' || card.getAttribute('data-category') === activeCategory;
            card.hidden = !(matchText && matchCat);
            if (!card.hidden) {
                visible += 1;
            }
        });
        if (noResult) {
            noResult.hidden = visible > 0;
        }
    }

    if (search) {
        search.addEventListener('input', filter);
        search.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    }
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            activeCategory = chip.getAttribute('data-category') || '';
            chips.forEach(function (other) {
                other.classList.toggle('is-on', other === chip);
            });
            filter();
        });
    });

    restore();
    // Arrivée depuis la fiche d'un produit (?ajouter=ID) : il est mis au panier et la commande s'ouvre.
    var wanted = new URLSearchParams(window.location.search).get('ajouter');
    if (wanted) {
        cards.forEach(function (card) {
            var field = input(card);
            if (field && card.getAttribute('data-product') === wanted && parseInt(field.value || '0', 10) < 1) {
                field.value = clamp(card, 1);
            }
        });
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', window.location.pathname);
        }
    }
    render();
    if (document.getElementById('erreurs') || wanted) {
        open();
    }
}());
