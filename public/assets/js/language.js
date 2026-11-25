/*
 * Langue de la plateforme : français (par défaut) ou anglais, traduit par GTranslate.
 * Réglages du widget GTranslate (il lit window.gtranslateSettings au chargement), puis boutons FR / EN.
 */
(function () {
    'use strict';

    window.gtranslateSettings = {
        default_language: 'fr',
        languages: ['fr', 'en'],
        wrapper_selector: '.gtranslate_wrapper',
        native_language_names: true,
        detect_browser_language: false
    };

    function current() {
        var match = document.cookie.match(/(?:^|;\s*)googtrans=([^;]*)/);
        var value = match ? decodeURIComponent(match[1]) : '';
        return /\/en$/.test(value) ? 'en' : 'fr';
    }

    function remember(lang) {
        // On écrit toujours le choix (« /fr/fr » ou « /fr/en ») au lieu d'effacer le cookie : Google Translate pose
        // aussi le sien, parfois sur le domaine parent, et un cookie oublié referait passer la page en anglais.
        var value = '/fr/' + lang;
        var expires = new Date(Date.now() + 365 * 864e5).toUTCString();
        var host = location.hostname;
        var base = 'googtrans=' + value + '; expires=' + expires + '; path=/';
        document.cookie = base;
        document.cookie = base + '; domain=' + host;
        if (host.indexOf('.') > 0 && !/^\d+\.\d+\.\d+\.\d+$/.test(host)) {
            document.cookie = base + '; domain=.' + host.replace(/^www\./, '');
        }
    }

    // Glossaire (language-glossary.js) : un texte dont le contenu est exactement une entrée est entouré d'un
    // <span data-en> « notranslate », pour que l'anglais écrit à la main l'emporte sur Google Translate.
    function glossary(root) {
        var words = window.SESAM_GLOSSARY || {};
        if (!root || !document.body) {
            return;
        }
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                var parent = node.parentElement;
                if (!parent || parent.closest('script, style, textarea, input, select, option, .notranslate, [translate="no"], [contenteditable]')) {
                    return NodeFilter.FILTER_REJECT;
                }
                return Object.prototype.hasOwnProperty.call(words, node.nodeValue.trim()) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP;
            }
        });
        var nodes = [];
        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }
        nodes.forEach(function (node) {
            var text = node.nodeValue;
            var key = text.trim();
            var span = document.createElement('span');
            span.className = 'notranslate';
            span.setAttribute('translate', 'no');
            span.setAttribute('data-en', words[key]);
            span.textContent = key;
            var start = text.indexOf(key);
            var parent = node.parentNode;
            if (start > 0) {
                parent.insertBefore(document.createTextNode(text.slice(0, start)), node);
            }
            parent.insertBefore(span, node);
            var rest = text.slice(start + key.length);
            if (rest !== '') {
                parent.insertBefore(document.createTextNode(rest), node);
            }
            parent.removeChild(node);
        });
    }

    // Contenu ajouté après coup (listes filtrées, fenêtres) : le glossaire s'applique aussi, en anglais seulement.
    var observer = null;
    function watch(lang) {
        if (lang !== 'en' || observer || !window.MutationObserver || !document.body) {
            return;
        }
        var pending = false;
        observer = new MutationObserver(function () {
            if (pending) {
                return;
            }
            pending = true;
            window.setTimeout(function () {
                pending = false;
                if (current() === 'en') {
                    glossary(document.body);
                    overrides('en');
                }
            }, 50);
        });
        observer.observe(document.body, {childList: true, subtree: true});
    }

    // Textes traduits à la main (Html::en côté PHP) : on remplace le texte français par l'anglais, et inversement.
    function overrides(lang) {
        document.querySelectorAll('[data-en]').forEach(function (el) {
            if (!el.hasAttribute('data-fr')) {
                el.setAttribute('data-fr', el.textContent);
            }
            var text = lang === 'en' ? el.getAttribute('data-en') : el.getAttribute('data-fr');
            if (el.textContent !== text) {
                el.textContent = text;
            }
        });
    }

    function paint(lang) {
        if (lang === 'en') {
            glossary(document.body);
            watch(lang);
        }
        overrides(lang);
        document.querySelectorAll('[data-lang-switch] [data-lang]').forEach(function (button) {
            button.setAttribute('aria-pressed', button.getAttribute('data-lang') === lang ? 'true' : 'false');
        });
    }

    function choose(lang) {
        if (lang === current()) {
            return;
        }
        remember(lang);
        paint(lang);
        // Le widget GTranslate traduit la page sans la recharger ; s'il n'est pas encore prêt, on recharge.
        if (typeof window.doGTranslate === 'function') {
            window.doGTranslate('fr|' + lang);
            document.documentElement.lang = lang;
        } else {
            location.reload();
        }
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-lang-switch] [data-lang]') : null;
        if (button) {
            event.preventDefault();
            choose(button.getAttribute('data-lang'));
        }
    });

    function init() {
        paint(current());
        document.documentElement.lang = current();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
