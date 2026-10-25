/* Réservation en ligne d'un salon : jours, créneaux libres, personnes compatibles, résumé. Sans JavaScript, un champ date/heure suffit. */
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
    if (!daysBox || !slotsBox || !startsAt) {
        return;
    }

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
        daysBox.querySelectorAll('button').forEach(function (b) {
            b.setAttribute('aria-pressed', b.getAttribute('data-day') === day ? 'true' : 'false');
        });
    }

    function checked(name) {
        return form.querySelector('[name="' + name + '"]:checked');
    }

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

    function updateSummary() {
        var service = checked('service_id');
        var text = 'Choisissez une prestation et un créneau.';
        var label = 'Réserver mon rendez-vous';
        if (service) {
            text = service.getAttribute('data-name') + ' · ' + service.getAttribute('data-duration') + ' · ' + service.getAttribute('data-price');
            if (startsAt.value) {
                var d = new Date(startsAt.value.replace(' ', 'T') + ':00Z');
                var days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
                text += ' — ' + days[d.getUTCDay()] + ' ' + String(d.getUTCDate()).padStart(2, '0') + '/' + String(d.getUTCMonth() + 1).padStart(2, '0') + ' à ' + startsAt.value.slice(11, 16);
            }
            if (parseInt(service.getAttribute('data-deposit') || '0', 10) >= 100) {
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
    }

    function note(text) {
        slotsBox.innerHTML = '';
        var p = document.createElement('p');
        p.textContent = text;
        slotsBox.appendChild(p);
    }

    function load() {
        var service = checked('service_id');
        if (!service) {
            note('Choisissez d\'abord une prestation.');
            updateSummary();
            return;
        }
        var staff = checked('staff_id');
        var params = new URLSearchParams({ prestation: service.value, date: day });
        if (staff && staff.value) {
            params.set('personne', staff.value);
        }
        note('Recherche des créneaux libres…');
        fetch(url + '?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var slots = (data && data.slots) || [];
                slotsBox.innerHTML = '';
                if (!slots.length) {
                    note('Plus de créneau libre ce jour-là. Essayez un autre jour.');
                    return;
                }
                var keep = false;
                slots.forEach(function (slot) {
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
                    });
                    slotsBox.appendChild(b);
                });
                if (!keep) {
                    startsAt.value = '';
                }
                updateSummary();
            })
            .catch(function () {
                note('Impossible de charger les créneaux. Réessayez dans un instant.');
            });
    }

    daysBox.addEventListener('click', function (event) {
        var b = event.target instanceof Element ? event.target.closest('[data-day]') : null;
        if (!b) {
            return;
        }
        day = b.getAttribute('data-day');
        daysBox.querySelectorAll('button').forEach(function (o) {
            o.setAttribute('aria-pressed', o === b ? 'true' : 'false');
        });
        startsAt.value = '';
        load();
    });

    form.addEventListener('change', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLInputElement)) {
            return;
        }
        if (target.name === 'service_id') {
            filterPeople();
            load();
        } else if (target.name === 'staff_id') {
            load();
        }
    });

    form.addEventListener('submit', function (event) {
        if (!startsAt.value) {
            event.preventDefault();
            note('Choisissez un créneau ci-dessus.');
            slotsBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    filterPeople();
    load();
    updateSummary();
}());
