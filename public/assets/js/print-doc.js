/* Documents imprimables (quittances) : bouton « Imprimer ». */
(function () {
    'use strict';
    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    });
}());
