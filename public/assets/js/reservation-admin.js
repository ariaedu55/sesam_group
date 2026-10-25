/* Espace salon : créneaux libres au clic, filtres qui s'appliquent tout seuls. */
(function () {
    'use strict';

    document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            if (select.form) {
                select.form.submit();
            }
        });
    });

    document.querySelectorAll('[data-slot-picker]').forEach(function (picker) {
        var form = picker.closest('form');
        if (!form) {
            return;
        }
        var url = picker.getAttribute('data-slot-picker') || '';
        var service = form.querySelector('[name="service_id"]');
        var fixedService = picker.getAttribute('data-service') || '';
        var staff = form.querySelector('[name="staff_id"]');
        var date = picker.querySelector('[data-slot-date]');
        var grid = picker.querySelector('[data-slot-grid]');
        var target = form.querySelector('[name="starts_at"]');
        var manual = picker.querySelector('[data-slot-manual]');
        if (!date || !grid || !target) {
            return;
        }
        picker.classList.add('is-enhanced');

        function message(text) {
            grid.innerHTML = '';
            var p = document.createElement('p');
            p.className = 'slot-empty';
            p.textContent = text;
            grid.appendChild(p);
        }

        function load() {
            var serviceId = fixedService || (service ? service.value : '');
            if (!serviceId || !date.value) {
                message('Choisissez une prestation et une date pour voir les créneaux libres.');
                return;
            }
            message('Recherche des créneaux…');
            var params = new URLSearchParams({ prestation: serviceId, date: date.value });
            if (staff && staff.value) {
                params.set('personne', staff.value);
            }
            fetch(url + '?' + params.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var slots = (data && data.slots) || [];
                    grid.innerHTML = '';
                    if (!slots.length) {
                        message('Aucun créneau libre ce jour-là. Essayez une autre date ou saisissez l\'heure à la main.');
                        return;
                    }
                    slots.forEach(function (slot) {
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.textContent = slot.time;
                        b.setAttribute('aria-pressed', target.value.replace('T', ' ').slice(0, 16) === slot.starts_at.slice(0, 16) ? 'true' : 'false');
                        b.addEventListener('click', function () {
                            target.value = slot.starts_at.slice(0, 16).replace(' ', 'T');
                            if (staff && !staff.value && slot.staff_id) {
                                staff.value = String(slot.staff_id);
                            }
                            grid.querySelectorAll('button').forEach(function (other) {
                                other.setAttribute('aria-pressed', other === b ? 'true' : 'false');
                            });
                        });
                        grid.appendChild(b);
                    });
                })
                .catch(function () {
                    message('Impossible de charger les créneaux. Saisissez l\'heure à la main.');
                });
        }

        [service, staff, date].forEach(function (field) {
            if (field) {
                field.addEventListener('change', load);
            }
        });
        if (manual) {
            manual.hidden = false;
        }
        load();
    });

    // Nom de la cliente : suggestions en temps réel parmi les clientes déjà enregistrées.
    document.querySelectorAll('[data-client-picker]').forEach(function (picker) {
        var url = picker.getAttribute('data-client-picker') || '';
        var idField = picker.querySelector('[data-client-id]');
        var name = picker.querySelector('[data-client-name]');
        var phone = picker.querySelector('[data-client-phone]');
        var list = picker.querySelector('[data-client-list]');
        var picked = picker.querySelector('[data-client-picked]');
        var clear = picker.querySelector('[data-client-clear]');
        if (!idField || !name || !phone || !list) {
            return;
        }
        var items = [];
        var active = -1;
        var timer = null;
        var controller = null;
        var lastQuery = '';

        function close() {
            list.hidden = true;
            list.innerHTML = '';
            name.setAttribute('aria-expanded', 'false');
            name.removeAttribute('aria-activedescendant');
            items = [];
            active = -1;
        }

        function setPicked(client) {
            idField.value = client ? String(client.id) : '';
            if (picked) {
                picked.hidden = !client;
            }
            phone.readOnly = !!(client && client.phone);
            picker.classList.toggle('is-picked', !!client);
        }

        function choose(client) {
            name.value = client.name;
            phone.value = client.phone || '';
            setPicked(client);
            close();
            if (!client.phone) {
                phone.focus();
            }
        }

        // Le texte tapé est mis en gras dans le nom, sans jamais injecter de HTML.
        function highlighted(text, query) {
            var span = document.createElement('span');
            var at = text.toLowerCase().indexOf(query.toLowerCase());
            if (query === '' || at < 0) {
                span.textContent = text;
                return span;
            }
            span.appendChild(document.createTextNode(text.slice(0, at)));
            var mark = document.createElement('mark');
            mark.textContent = text.slice(at, at + query.length);
            span.appendChild(mark);
            span.appendChild(document.createTextNode(text.slice(at + query.length)));
            return span;
        }

        function move(index) {
            var options = list.querySelectorAll('[role="option"]');
            if (!options.length) {
                return;
            }
            active = (index + options.length) % options.length;
            options.forEach(function (option, i) {
                option.setAttribute('aria-selected', i === active ? 'true' : 'false');
            });
            name.setAttribute('aria-activedescendant', options[active].id);
            options[active].scrollIntoView({ block: 'nearest' });
        }

        function render(clients, query) {
            list.innerHTML = '';
            items = clients;
            active = -1;
            if (!clients.length) {
                var empty = document.createElement('li');
                empty.className = 'client-suggest-empty';
                empty.textContent = 'Aucune cliente trouvée : elle sera créée à l\'enregistrement.';
                list.appendChild(empty);
            }
            clients.forEach(function (client, i) {
                var li = document.createElement('li');
                li.id = 'client-option-' + i;
                li.setAttribute('role', 'option');
                li.setAttribute('aria-selected', 'false');
                var initials = document.createElement('span');
                initials.className = 'client-suggest-avatar';
                initials.setAttribute('aria-hidden', 'true');
                initials.textContent = client.name.trim().slice(0, 2).toUpperCase();
                var text = document.createElement('span');
                text.className = 'client-suggest-text';
                var strong = document.createElement('strong');
                strong.appendChild(highlighted(client.name, query));
                var small = document.createElement('small');
                small.textContent = client.phone || client.email || 'Pas de numéro';
                text.appendChild(strong);
                text.appendChild(small);
                li.appendChild(initials);
                li.appendChild(text);
                // mousedown plutôt que click : le choix passe avant la perte du focus.
                li.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    choose(client);
                });
                list.appendChild(li);
            });
            list.hidden = false;
            name.setAttribute('aria-expanded', 'true');
        }

        function search() {
            var query = name.value.trim();
            if (query.length < 1) {
                close();
                return;
            }
            if (controller) {
                controller.abort();
            }
            controller = typeof AbortController === 'function' ? new AbortController() : null;
            lastQuery = query;
            fetch(url + '?' + new URLSearchParams({ q: query }).toString(), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: controller ? controller.signal : undefined
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (query !== lastQuery || document.activeElement !== name) {
                        return;
                    }
                    render((data && data.clients) || [], query);
                })
                .catch(function () { /* recherche annulée ou hors ligne : la saisie libre reste possible */ });
        }

        name.addEventListener('input', function () {
            if (idField.value !== '') {
                // Le nom a été modifié : ce n'est plus la cliente choisie.
                setPicked(null);
            }
            window.clearTimeout(timer);
            timer = window.setTimeout(search, 160);
        });
        name.addEventListener('focus', function () {
            if (idField.value === '' && name.value.trim() !== '') {
                search();
            }
        });
        name.addEventListener('blur', function () {
            window.setTimeout(close, 120);
        });
        name.addEventListener('keydown', function (event) {
            if (list.hidden) {
                return;
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                move(active + 1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                move(active - 1);
            } else if (event.key === 'Enter' && active >= 0 && items[active]) {
                event.preventDefault();
                choose(items[active]);
            } else if (event.key === 'Escape') {
                close();
            }
        });
        if (clear) {
            clear.addEventListener('click', function () {
                setPicked(null);
                name.value = '';
                phone.value = '';
                name.focus();
            });
        }
        setPicked(idField.value !== '' ? { id: idField.value, phone: phone.value } : null);
    });
}());
