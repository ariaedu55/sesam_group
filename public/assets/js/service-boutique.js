/* Page /services/boutique : vitrine de démonstration (rayons, panier animé, message WhatsApp prêt à envoyer). */
(function () {
    'use strict';

    var shop = document.querySelector('[data-bx-shop]');
    if (!shop) {
        return;
    }
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var cart = shop.querySelector('[data-bx-cart]');
    var lines = cart.querySelector('[data-bx-lines]');
    var count = cart.querySelector('[data-bx-count]');
    var total = cart.querySelector('[data-bx-total]');
    var order = cart.querySelector('[data-bx-order]');
    var chat = cart.querySelector('[data-bx-chat]');
    var message = cart.querySelector('[data-bx-message]');
    var items = [];

    function money(value) {
        return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
    }

    // ---------------------------------------------------------------- Rayons
    var tabs = Array.prototype.slice.call(shop.querySelectorAll('[data-bx-filter]'));
    var cards = Array.prototype.slice.call(shop.querySelectorAll('[data-bx-cat]'));
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var filter = tab.getAttribute('data-bx-filter');
            tabs.forEach(function (other) {
                var on = other === tab;
                other.classList.toggle('is-on', on);
                other.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            cards.forEach(function (card, index) {
                var show = filter === 'all' || card.getAttribute('data-bx-cat') === filter;
                card.hidden = !show;
                if (show && !reduced) {
                    card.classList.remove('is-pop');
                    void card.offsetWidth;
                    card.style.setProperty('--i', String(index % 6));
                    card.classList.add('is-pop');
                }
            });
        });
    });

    // ---------------------------------------------------------------- Panier
    function render() {
        var sum = 0;
        var qty = 0;
        lines.innerHTML = '';
        if (!items.length) {
            var empty = document.createElement('li');
            empty.className = 'bx-cart-empty';
            empty.textContent = 'Ajoutez un produit pour remplir le panier.';
            lines.appendChild(empty);
        }
        items.forEach(function (item) {
            sum += item.price * item.qty;
            qty += item.qty;
            var li = document.createElement('li');
            var label = document.createElement('span');
            label.textContent = item.qty + ' × ' + item.name;
            var price = document.createElement('b');
            price.textContent = money(item.price * item.qty);
            li.appendChild(label);
            li.appendChild(price);
            lines.appendChild(li);
        });
        count.textContent = String(qty);
        total.textContent = money(sum);
        order.disabled = qty === 0;
        chat.hidden = true;
        return sum;
    }

    function fly(from) {
        if (reduced) {
            return;
        }
        var start = from.getBoundingClientRect();
        var end = cart.querySelector('.bx-cart-icon').getBoundingClientRect();
        var dot = document.createElement('span');
        dot.className = 'bx-fly';
        dot.style.left = start.left + start.width / 2 + 'px';
        dot.style.top = start.top + start.height / 2 + 'px';
        document.body.appendChild(dot);
        var dx = end.left + end.width / 2 - (start.left + start.width / 2);
        var dy = end.top + end.height / 2 - (start.top + start.height / 2);
        dot.animate([
            { transform: 'translate(-50%, -50%) scale(1)', opacity: 1 },
            { transform: 'translate(calc(-50% + ' + dx * 0.5 + 'px), calc(-50% + ' + (dy * 0.5 - 80) + 'px)) scale(1.2)', opacity: 1, offset: 0.5 },
            { transform: 'translate(calc(-50% + ' + dx + 'px), calc(-50% + ' + dy + 'px)) scale(0.4)', opacity: 0.6 }
        ], { duration: 650, easing: 'cubic-bezier(0.3, 0.7, 0.4, 1)' }).onfinish = function () {
            dot.remove();
            cart.classList.remove('is-bump');
            void cart.offsetWidth;
            cart.classList.add('is-bump');
        };
    }

    shop.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        var add = target ? target.closest('[data-bx-add]') : null;
        if (!add) {
            return;
        }
        var name = add.getAttribute('data-name');
        var price = parseInt(add.getAttribute('data-price') || '0', 10);
        var existing = items.filter(function (item) { return item.name === name; })[0];
        if (existing) {
            existing.qty += 1;
        } else {
            items.push({ name: name, price: price, qty: 1 });
        }
        render();
        fly(add);
        add.classList.add('is-added');
        add.lastChild.textContent = ' Ajouté';
        window.setTimeout(function () {
            add.classList.remove('is-added');
            add.lastChild.textContent = ' Ajouter';
        }, 1200);
    });

    order.addEventListener('click', function () {
        var sum = items.reduce(function (acc, item) { return acc + item.price * item.qty; }, 0);
        message.textContent = 'Bonjour ! Je souhaite commander : ' + items.map(function (item) {
            return item.qty + ' × ' + item.name;
        }).join(', ') + '. Total : ' + money(sum) + '.';
        chat.hidden = false;
        if (!reduced) {
            chat.classList.remove('is-in');
            void chat.offsetWidth;
            chat.classList.add('is-in');
        }
    });

    render();
}());
