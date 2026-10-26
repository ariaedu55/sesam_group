/* Espace Immo : le choix d'un logement pré-remplit loyer, charges et caution ; un locataire existant masque la saisie. */
(function () {
    'use strict';

    document.querySelectorAll('[data-lease-form]').forEach(function (form) {
        var unit = form.querySelector('[data-lease-unit]');
        var client = form.querySelector('[data-lease-client]');
        var fresh = form.querySelector('[data-lease-new]');
        var fields = {
            rent: form.querySelector('[data-lease-rent]'),
            charges: form.querySelector('[data-lease-charges]'),
            deposit: form.querySelector('[data-lease-deposit]')
        };

        if (unit) {
            unit.addEventListener('change', function () {
                var option = unit.options[unit.selectedIndex];
                if (!option || !option.value) {
                    return;
                }
                Object.keys(fields).forEach(function (key) {
                    if (fields[key]) {
                        fields[key].value = option.getAttribute('data-' + key) || '0';
                    }
                });
            });
        }

        function toggle() {
            if (!client || !fresh) {
                return;
            }
            var existing = client.value !== '';
            fresh.hidden = existing;
            fresh.querySelectorAll('input').forEach(function (input) {
                input.disabled = existing;
            });
        }
        if (client) {
            client.addEventListener('change', toggle);
            toggle();
        }
    });

    document.querySelectorAll('[data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            var target = document.getElementById(input.getAttribute('data-preview') || '');
            var file = input.files && input.files[0];
            if (!target || !file || !window.FileReader || file.type.indexOf('image/') !== 0) {
                return;
            }
            var reader = new FileReader();
            reader.onload = function () {
                var img = document.createElement('img');
                img.className = target.className;
                img.id = target.id;
                img.alt = '';
                img.src = String(reader.result || '');
                target.replaceWith(img);
                target = img;
            };
            reader.readAsDataURL(file);
        });
    });
}());
