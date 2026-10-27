/*
 * Réservation en ligne d'un salon, guidée pas à pas : prestation → personne → jour et heure → coordonnées.
 * Une étape à la fois, passage automatique à la suivante après un choix, créneaux groupés (matin, après-midi, soir),
 * recherche du prochain jour libre, récapitulatif modifiable. Sans JavaScript, tout le formulaire reste visible
 * et un champ date/heure suffit.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-salon]');
    if (!form) {
        return;
    }
    var url = form.getAttribute('data-salon') || '';
    var daysBox = form.querySelector('[data-days]');
    var slotsBox = form.querySelector('[data-slots]');
    var manual = form.querySelector('[data-manual]');
    var startsAt = form.querySelector('[name="starts_at"]');
    var summary = form.querySelector('[data-summary]');
    var submit = form.querySelector('[data-submit]');
    var steps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
    var progress = form.querySelector('[data-progress]');
    if (!daysBox || !slotsBox || !startsAt) {
        return;
    }
    var WEEKDAYS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    var MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    daysBox.hidden = false;
    slotsBox.hidden = false;
    if (manual) {
        manual.hidden = true;
        startsAt.type = 'hidden';
        startsAt.required = false;
    }

    var day = (daysBox.querySelector('[aria-pressed="true"]') || daysBox.querySelector('button')).getAttribute('data-day');
    if (startsAt.value) {
        day = startsAt.value.slice(0, 10);
    }
    markDay(day);

    function checked(name) {
        return form.querySelector('[name="' + name + '"]:checked');
    }

    function field(name) {
        return form.querySelector('[name="' + name + '"]');
    }

    function longDate(value) {
        var d = new Date(value.slice(0, 10) + 'T00:00:00Z');
        return WEEKDAYS[d.getUTCDay()] + ' ' + d.getUTCDate() + ' ' + MONTHS[d.getUTCMonth()];
    }

    function whenText() {
        if (!startsAt.value) {
            return '';
        }
        var text = longDate(startsAt.value) + ' à ' + startsAt.value.slice(11, 16).replace(':', 'h');
        return text.charAt(0).toUpperCase() + text.slice(1);
    }

    function markDay(value) {
        daysBox.querySelectorAll('button').forEach(function (b) {
            var on = b.getAttribute('data-day') === value;
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (on && b.scrollIntoView && form.classList.contains('is-ready')) {
                b.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
            }
        });
    }

    // ------------------------------------------------------------------ Personnes compatibles

    function filterPeople() {
        var service = checked('service_id');
        var id = service ? service.value : '';
        form.querySelectorAll('[data-services]').forEach(function (person) {
            var list = person.getAttribute('data-services');
            var ok = !id || !list || list.split(',').indexOf(id) !== -1;
            person.hidden = !ok;
            var input = person.querySelector('input');
            if (!ok && input && input.checked) {
                var any = form.querySelector('[name="staff_id"][value=""]');
                if (any) {
                    any.checked = true;
                }
            }
        });
    }

    // ------------------------------------------------------------------ Résumé et récapitulatif

    function setText(selector, text) {
        var el = form.querySelector(selector);
        if (el) {
            el.textContent = text;
        }
    }

    function updateSummary() {
        var service = checked('service_id');
        var staff = checked('staff_id');
        var text = 'Choisissez une prestation et un créneau.';
        var label = 'Réserver mon rendez-vous';
        var deposit = service ? parseInt(service.getAttribute('data-deposit') || '0', 10) : 0;
        if (service) {
            text = service.getAttribute('data-name') + ' · ' + service.getAttribute('data-duration') + ' · ' + service.getAttribute('data-price');
            if (startsAt.value) {
                text += ' — ' + whenText();
            }
            if (deposit >= 100) {
                label = 'Réserver et payer l\'acompte';
            }
        }
        if (summary) {
            summary.textContent = text;
        }
        if (submit) {
            submit.textContent = label;
            submit.disabled = !(service && startsAt.value);
        }
        var final = form.querySelector('[data-submit-final]');
        if (final) {
            final.textContent = deposit >= 100 ? 'Confirmer et payer l\'acompte' : 'Confirmer mon rendez-vous';
        }
        setText('[data-recap-service]', service ? service.getAttribute('data-name') + ' (' + service.getAttribute('data-duration') + ')' : '—');
        setText('[data-recap-staff]', staff && staff.value ? (staff.closest('label').querySelector('strong') || {}).textContent || 'Peu importe' : 'Peu importe');
        setText('[data-recap-when]', startsAt.value ? whenText() : '—');
        setText('[data-recap-price]', service ? service.getAttribute('data-price') + (deposit >= 100 ? ' · acompte ' + Math.round(deposit / 100).toLocaleString('fr-FR') + ' FCFA à régler maintenant' : '') : '—');
        refreshNav();
    }

    // ------------------------------------------------------------------ Créneaux

    function note(text, action) {
        slotsBox.innerHTML = '';
        var p = document.createElement('p');
        p.className = 'sl-slots-note';
        p.textContent = text;
        if (action) {
            p.appendChild(document.createElement('br'));
            p.appendChild(action);
        }
        slotsBox.appendChild(p);
    }

    function params(date) {
        var service = checked('service_id');
        var staff = checked('staff_id');
        var query = new URLSearchParams({ prestation: service ? service.value : '', date: date });
        if (staff && staff.value) {
            query.set('personne', staff.value);
        }
        return query.toString();
    }

    function fetchSlots(date) {
        return fetch(url + '?' + params(date), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { return (data && data.slots) || []; });
    }

    var requestId = 0;

    function load() {
        var service = checked('service_id');
        if (!service) {
            note('Choisissez d\'abord une prestation.');
            updateSummary();
            return;
        }
        var current = ++requestId;
        slotsBox.setAttribute('aria-busy', 'true');
        note('Recherche des horaires libres…');
        fetchSlots(day).then(function (slots) {
            if (current !== requestId) {
                return;
            }
            slotsBox.removeAttribute('aria-busy');
            if (!slots.length) {
                startsAt.value = '';
                updateSummary();
                findNextDay(current);
                return;
            }
            render(slots);
        }).catch(function () {
            slotsBox.removeAttribute('aria-busy');
            note('Impossible de charger les horaires. Vérifiez votre connexion et réessayez.');
        });
    }

    function render(slots) {
        slotsBox.innerHTML = '';
        var groups = [['Matin', []], ['Après-midi', []], ['Soir', []]];
        slots.forEach(function (slot) {
            var hour = parseInt(slot.time.slice(0, 2), 10);
            groups[hour < 12 ? 0 : (hour < 17 ? 1 : 2)][1].push(slot);
        });
        var keep = false;
        groups.forEach(function (group) {
            if (!group[1].length) {
                return;
            }
            var title = document.createElement('p');
            title.className = 'sl-slot-group';
            title.textContent = group[0];
            var grid = document.createElement('div');
            grid.className = 'sl-slot-grid';
            group[1].forEach(function (slot) {
                var value = slot.starts_at.slice(0, 16).replace(' ', 'T');
                var b = document.createElement('button');
                b.type = 'button';
                b.textContent = slot.time;
                b.setAttribute('aria-pressed', startsAt.value === value ? 'true' : 'false');
                if (startsAt.value === value) {
                    keep = true;
                }
                b.addEventListener('click', function () {
                    startsAt.value = value;
                    slotsBox.querySelectorAll('button').forEach(function (o) {
                        o.setAttribute('aria-pressed', o === b ? 'true' : 'false');
                    });
                    updateSummary();
                    advanceFrom('slot');
                });
                grid.appendChild(b);
            });
            slotsBox.appendChild(title);
            slotsBox.appendChild(grid);
        });
        if (!keep) {
            startsAt.value = '';
        }
        updateSummary();
    }

    // Jour complet ou fermé : on cherche tout seul le prochain jour qui a de la place.
    function findNextDay(current) {
        var buttons = Array.prototype.slice.call(daysBox.querySelectorAll('[data-day]'));
        var from = buttons.findIndex(function (b) { return b.getAttribute('data-day') === day; });
        var candidates = buttons.slice(from + 1, from + 15);
        note('Plus d\'horaire libre ce jour-là. Recherche du prochain jour disponible…');
        (function next(i) {
            if (current !== requestId) {
                return;
            }
            if (i >= candidates.length) {
                note('Aucun horaire libre dans les prochains jours. Écrivez au salon sur WhatsApp, il vous trouvera une place.');
                return;
            }
            var date = candidates[i].getAttribute('data-day');
            fetchSlots(date).then(function (slots) {
                if (current !== requestId) {
                    return;
                }
                if (!slots.length) {
                    next(i + 1);
                    return;
                }
                var go = document.createElement('button');
                go.type = 'button';
                go.className = 'sl-btn sl-btn-next';
                go.textContent = 'Voir le ' + longDate(date) + ' (' + slots.length + ' horaire' + (slots.length > 1 ? 's' : '') + ')';
                go.addEventListener('click', function () {
                    day = date;
                    markDay(day);
                    load();
                });
                note('Plus d\'horaire libre ce jour-là.', go);
            }).catch(function () {
                note('Impossible de charger les horaires. Réessayez dans un instant.');
            });
        }(0));
    }

    daysBox.addEventListener('click', function (event) {
        var b = event.target instanceof Element ? event.target.closest('[data-day]') : null;
        if (!b) {
            return;
        }
        day = b.getAttribute('data-day');
        markDay(day);
        startsAt.value = '';
        load();
    });

    // ------------------------------------------------------------------ Étapes

    var wizard = steps.length > 1 && !!progress;
    var index = 0;

    function stepValid(i) {
        var name = steps[i] ? steps[i].getAttribute('data-step') : '';
        if (name === 'service') {
            return !!checked('service_id');
        }
        if (name === 'slot') {
            return !!startsAt.value;
        }
        if (name === 'contact') {
            var fullName = field('full_name');
            var phone = field('phone');
            return !!(fullName && fullName.value.trim().length >= 2 && phone && phone.value.replace(/\D/g, '').length >= 8);
        }
        return true;
    }

    function firstInvalid() {
        for (var i = 0; i < steps.length; i++) {
            if (!stepValid(i)) {
                return i;
            }
        }
        return steps.length - 1;
    }

    function refreshNav() {
        if (!wizard) {
            return;
        }
        steps.forEach(function (step, i) {
            var next = step.querySelector('[data-next]');
            if (next) {
                next.disabled = !stepValid(i);
            }
        });
        var items = progress.querySelectorAll('[data-progress-item]');
        items.forEach(function (item, i) {
            item.classList.toggle('is-current', i === index);
            item.classList.toggle('is-done', i < index || (i !== index && stepValid(i) && i < firstInvalid()));
            var button = item.querySelector('button');
            if (button) {
                button.disabled = i > firstInvalid() && i !== index;
                if (i === index) {
                    button.setAttribute('aria-current', 'step');
                } else {
                    button.removeAttribute('aria-current');
                }
            }
        });
    }

    function go(i, focus) {
        if (!wizard) {
            return;
        }
        index = Math.max(0, Math.min(steps.length - 1, i));
        steps.forEach(function (step, n) {
            step.hidden = n !== index;
        });
        form.setAttribute('data-current-step', steps[index].getAttribute('data-step') || '');
        if (steps[index].getAttribute('data-step') === 'slot' && !slotsBox.querySelector('button') && checked('service_id')) {
            load();
        }
        refreshNav();
        if (focus) {
            var top = form.getBoundingClientRect().top + window.pageYOffset - 70;
            window.scrollTo({ top: top, behavior: 'smooth' });
            var heading = steps[index].querySelector('h2');
            if (heading) {
                heading.setAttribute('tabindex', '-1');
                heading.focus({ preventScroll: true });
            }
        }
    }

    function advanceFrom(name) {
        if (!wizard || !steps[index] || steps[index].getAttribute('data-step') !== name) {
            return;
        }
        window.setTimeout(function () {
            if (stepValid(index)) {
                go(index + 1, true);
            }
        }, 280);
    }

    if (wizard) {
        form.classList.add('is-wizard');
        progress.hidden = false;
        form.querySelectorAll('[data-step-nav]').forEach(function (nav) {
            nav.hidden = false;
        });
        var recap = form.querySelector('[data-recap]');
        if (recap) {
            recap.hidden = false;
        }
        form.addEventListener('click', function (event) {
            var target = event.target instanceof Element ? event.target : null;
            if (!target) {
                return;
            }
            if (target.closest('[data-next]')) {
                if (stepValid(index)) {
                    go(index + 1, true);
                }
            } else if (target.closest('[data-prev]')) {
                go(index - 1, true);
            } else if (target.closest('[data-goto]')) {
                var to = parseInt(target.closest('[data-goto]').getAttribute('data-goto'), 10);
                if (to <= firstInvalid() || to < index) {
                    go(to, true);
                }
            }
        });
        form.addEventListener('input', function (event) {
            var target = event.target;
            if (target instanceof HTMLInputElement && (target.name === 'full_name' || target.name === 'phone')) {
                target.removeAttribute('aria-invalid');
                refreshNav();
            }
        });
        // Les liens « Réserver » de la page ramènent au début du parcours si rien n'est encore choisi.
        document.querySelectorAll('a[href="#reserver"]').forEach(function (link) {
            link.addEventListener('click', function () {
                if (!checked('service_id')) {
                    go(0, false);
                }
            });
        });
    }

    form.addEventListener('change', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLInputElement)) {
            return;
        }
        if (target.name === 'service_id') {
            filterPeople();
            startsAt.value = '';
            slotsBox.innerHTML = '';
            updateSummary();
            if (!wizard) {
                load();
            }
        } else if (target.name === 'staff_id') {
            startsAt.value = '';
            slotsBox.innerHTML = '';
            updateSummary();
            if (!wizard) {
                load();
            }
        }
    });

    // Un choix (même déjà coché, comme « Peu importe ») fait passer à l'étape suivante.
    form.addEventListener('click', function (event) {
        var target = event.target;
        if (target instanceof HTMLInputElement && target.type === 'radio') {
            advanceFrom(target.name === 'service_id' ? 'service' : (target.name === 'staff_id' ? 'staff' : ''));
        }
    });

    form.addEventListener('submit', function (event) {
        if (!startsAt.value) {
            event.preventDefault();
            if (wizard) {
                var slotStep = steps.findIndex(function (s) { return s.getAttribute('data-step') === 'slot'; });
                go(slotStep, true);
            }
            note('Choisissez un horaire.');
            return;
        }
        if (wizard && !stepValid(steps.length - 1)) {
            event.preventDefault();
            ['full_name', 'phone'].forEach(function (name) {
                var input = field(name);
                if (input && (name === 'phone' ? input.value.replace(/\D/g, '').length < 8 : input.value.trim().length < 2)) {
                    input.setAttribute('aria-invalid', 'true');
                }
            });
            var invalid = form.querySelector('[aria-invalid="true"]');
            if (invalid) {
                invalid.focus();
            }
            return;
        }
        form.querySelectorAll('[type="submit"]').forEach(function (b) {
            b.disabled = true;
            b.textContent = 'Envoi en cours…';
        });
    });

    filterPeople();
    updateSummary();
    if (wizard) {
        // Retour du serveur avec des erreurs : on rouvre la première étape à compléter.
        go(document.getElementById('erreurs') ? firstInvalid() : 0, false);
        if (checked('service_id') && steps[index].getAttribute('data-step') !== 'service') {
            load();
        }
    } else {
        load();
    }
    form.classList.add('is-ready');
}());
