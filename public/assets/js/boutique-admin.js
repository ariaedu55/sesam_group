/* Espace commerçant Boutique : copier le lien, ajouter des lignes de commande, aperçu des photos. */
(function () {
    'use strict';

    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.getElementById(button.getAttribute('data-copy') || '');
            if (!target) {
                return;
            }
            var value = target.value || target.textContent || '';
            var done = function () {
                var label = button.querySelector('[data-copy-label]') || button;
                var previous = label.textContent;
                label.textContent = 'Lien copié !';
                window.setTimeout(function () {
                    label.textContent = previous;
                }, 2000);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(done, function () {
                    target.select();
                });
            } else {
                target.select();
                try {
                    document.execCommand('copy');
                    done();
                } catch (e) {
                    /* l'utilisateur copie à la main */
                }
            }
        });
    });

    document.querySelectorAll('[data-share]').forEach(function (button) {
        if (!navigator.share) {
            return;
        }
        button.hidden = false;
        button.addEventListener('click', function () {
            navigator.share({
                title: button.getAttribute('data-share-title') || '',
                text: button.getAttribute('data-share-text') || '',
                url: button.getAttribute('data-share') || ''
            }).catch(function () {
                /* partage annulé */
            });
        });
    });

    var lines = document.querySelector('[data-lines]');
    var addLine = document.querySelector('[data-add-line]');
    if (lines && addLine) {
        addLine.addEventListener('click', function () {
            var first = lines.querySelector('[data-line]');
            if (!first) {
                return;
            }
            var copy = first.cloneNode(true);
            copy.querySelectorAll('select').forEach(function (select) {
                select.selectedIndex = 0;
            });
            copy.querySelectorAll('input').forEach(function (input) {
                input.value = '1';
            });
            lines.appendChild(copy);
            var select = copy.querySelector('select');
            if (select) {
                select.focus();
            }
        });
    }

    document.querySelectorAll('[data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            var target = document.getElementById(input.getAttribute('data-preview') || '');
            var file = input.files && input.files[0];
            if (!target || !file || !window.FileReader || file.type.indexOf('image/') !== 0) {
                return;
            }
            // Lecture en data: (autorisé par la CSP), pas en blob:.
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

    var clientSelect = document.querySelector('[data-client-select]');
    var newClient = document.querySelector('[data-new-client]');
    if (clientSelect && newClient) {
        var toggle = function () {
            newClient.hidden = clientSelect.value !== '';
        };
        clientSelect.addEventListener('change', toggle);
        toggle();
    }
}());
