/*
 * Choix du pays avec drapeaux : remplace l'affichage de chaque <select data-country-picker>.
 * Le <select> d'origine reste dans le formulaire (envoi, validation) et reçoit un événement « change »
 * à chaque choix, pour que la devise et les tarifs affichés suivent.
 * Drapeaux : image unique de la bibliothèque intl-tel-input (déjà utilisée par les champs téléphone).
 */
(function () {
    'use strict';

    var CURRENCY = {XOF: 'FCFA', FCFA: 'FCFA', USD: 'USD', EUR: 'EUR'};

    function flag(code) {
        var span = document.createElement('span');
        span.className = 'cp-flag iti__flag iti__' + code.toLowerCase();
        span.setAttribute('aria-hidden', 'true');
        return span;
    }

    function enhance(select) {
        if (select.getAttribute('data-cp-ready')) {
            return;
        }
        select.setAttribute('data-cp-ready', '1');
        var options = Array.prototype.filter.call(select.options, function (option) {
            return option.value !== '';
        });
        var labelledBy = select.id ? document.querySelector('label[for="' + select.id + '"]') : null;

        var root = document.createElement('div');
        root.className = 'cp';
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'cp-button';
        button.setAttribute('aria-haspopup', 'listbox');
        button.setAttribute('aria-expanded', 'false');
        if (labelledBy) {
            labelledBy.id = labelledBy.id || (select.id + '-label');
            button.setAttribute('aria-labelledby', labelledBy.id + ' ' + (select.id || 'cp') + '-value');
        } else {
            button.setAttribute('aria-label', 'Pays');
        }
        var list = document.createElement('ul');
        list.className = 'cp-list';
        list.setAttribute('role', 'listbox');
        list.id = (select.id || ('cp-' + Math.random().toString(36).slice(2))) + '-list';
        list.hidden = true;
        button.setAttribute('aria-controls', list.id);

        var previousGroup = null;
        options.forEach(function (option, index) {
            var currency = CURRENCY[option.getAttribute('data-currency') || ''] || '';
            var item = document.createElement('li');
            item.className = 'cp-option';
            item.id = list.id + '-' + option.value;
            item.setAttribute('role', 'option');
            item.setAttribute('data-value', option.value);
            item.setAttribute('data-index', String(index));
            if (previousGroup !== null && previousGroup !== currency) {
                item.classList.add('cp-group-start');
            }
            previousGroup = currency;
            item.appendChild(flag(option.value));
            var name = document.createElement('span');
            name.className = 'cp-name';
            name.textContent = option.textContent.trim();
            item.appendChild(name);
            if (currency) {
                var tag = document.createElement('span');
                tag.className = 'cp-currency';
                tag.textContent = currency;
                item.appendChild(tag);
            }
            list.appendChild(item);
        });

        select.classList.add('cp-native');
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');
        select.parentNode.insertBefore(root, select.nextSibling);
        root.appendChild(button);
        root.appendChild(list);
        var items = Array.prototype.slice.call(list.children);
        var active = -1;

        function render() {
            var option = select.options[select.selectedIndex];
            button.innerHTML = '';
            if (!option || option.value === '') {
                var empty = document.createElement('span');
                empty.className = 'cp-name';
                empty.textContent = 'Choisissez un pays';
                button.appendChild(empty);
            } else {
                button.appendChild(flag(option.value));
                var name = document.createElement('span');
                name.className = 'cp-name';
                name.id = (select.id || 'cp') + '-value';
                name.textContent = option.textContent.trim();
                button.appendChild(name);
                var currency = CURRENCY[option.getAttribute('data-currency') || ''] || '';
                if (currency) {
                    var tag = document.createElement('span');
                    tag.className = 'cp-currency';
                    tag.textContent = currency;
                    button.appendChild(tag);
                }
            }
            var chevron = document.createElement('span');
            chevron.className = 'cp-chevron';
            chevron.setAttribute('aria-hidden', 'true');
            button.appendChild(chevron);
            items.forEach(function (item) {
                item.setAttribute('aria-selected', item.getAttribute('data-value') === select.value ? 'true' : 'false');
            });
            button.disabled = select.disabled;
        }

        function highlight(index) {
            if (index < 0 || index >= items.length) {
                return;
            }
            if (active >= 0 && items[active]) {
                items[active].classList.remove('is-active');
            }
            active = index;
            items[active].classList.add('is-active');
            list.setAttribute('aria-activedescendant', items[active].id);
            items[active].scrollIntoView({block: 'nearest'});
        }

        function open() {
            if (select.disabled) {
                return;
            }
            list.hidden = false;
            root.classList.add('is-open');
            button.setAttribute('aria-expanded', 'true');
            list.tabIndex = -1;
            var current = items.findIndex(function (item) {
                return item.getAttribute('data-value') === select.value;
            });
            highlight(current >= 0 ? current : 0);
            list.focus();
        }

        function close(focusButton) {
            list.hidden = true;
            root.classList.remove('is-open');
            button.setAttribute('aria-expanded', 'false');
            if (focusButton) {
                button.focus();
            }
        }

        function choose(index) {
            var item = items[index];
            if (!item) {
                return;
            }
            if (select.value !== item.getAttribute('data-value')) {
                select.value = item.getAttribute('data-value');
                select.dispatchEvent(new Event('change', {bubbles: true}));
            }
            render();
            close(true);
        }

        button.addEventListener('click', function () {
            if (list.hidden) {
                open();
            } else {
                close(true);
            }
        });
        button.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                open();
            }
        });
        list.addEventListener('click', function (event) {
            var item = event.target.closest('.cp-option');
            if (item) {
                choose(parseInt(item.getAttribute('data-index'), 10));
            }
        });
        list.addEventListener('mousemove', function (event) {
            var item = event.target.closest('.cp-option');
            if (item) {
                highlight(parseInt(item.getAttribute('data-index'), 10));
            }
        });

        var typed = '';
        var typedTimer = null;
        list.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlight(Math.min(items.length - 1, active + 1));
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlight(Math.max(0, active - 1));
            } else if (event.key === 'Home') {
                event.preventDefault();
                highlight(0);
            } else if (event.key === 'End') {
                event.preventDefault();
                highlight(items.length - 1);
            } else if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                choose(active);
            } else if (event.key === 'Escape' || event.key === 'Tab') {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                }
                close(event.key === 'Escape');
            } else if (event.key.length === 1 && /\S/.test(event.key)) {
                // Taper les premières lettres d'un pays pour y aller.
                typed += event.key.toLowerCase();
                window.clearTimeout(typedTimer);
                typedTimer = window.setTimeout(function () { typed = ''; }, 700);
                var plain = function (text) {
                    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
                };
                var match = items.findIndex(function (item) {
                    return plain(item.querySelector('.cp-name').textContent).indexOf(plain(typed)) === 0;
                });
                if (match >= 0) {
                    highlight(match);
                }
            }
        });
        document.addEventListener('click', function (event) {
            if (!root.contains(event.target) && !list.hidden) {
                close(false);
            }
        });
        // Si un autre script change le pays (valeur rétablie, etc.), l'affichage suit.
        select.addEventListener('change', render);
        render();
    }

    function init() {
        document.querySelectorAll('select[data-country-picker]').forEach(enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
