/*
 * Notifications push SESAM (Firebase Cloud Messaging).
 * - Proposition discrète d'activer les notifications sur l'appareil (une fois, puis plus pendant 14 jours si refusée).
 * - Jeton d'appareil enregistré auprès de SESAM, actualisé une fois par jour.
 * - Application ouverte : la notification s'affiche en bandeau et la cloche se met à jour.
 */
/* global firebase */
(function () {
    'use strict';

    var root = document.querySelector('[data-push]');
    if (!root) {
        return;
    }
    var config = {};
    try {
        config = JSON.parse(root.getAttribute('data-config') || '{}');
    } catch (error) {
        return;
    }
    var vapidKey = root.getAttribute('data-vapid') || '';
    var swUrl = root.getAttribute('data-sw') || '';
    var scope = root.getAttribute('data-scope') || '/';
    var registerUrl = root.getAttribute('data-register') || '';
    var unregisterUrl = root.getAttribute('data-unregister') || '';
    var csrf = root.getAttribute('data-csrf') || '';
    var prompt = document.querySelector('[data-push-prompt]');
    var statusEls = document.querySelectorAll('[data-push-status]');
    var storage = {
        get: function (key) {
            try { return window.localStorage.getItem(key); } catch (error) { return null; }
        },
        set: function (key, value) {
            try { window.localStorage.setItem(key, value); } catch (error) { /* navigation privée */ }
        },
        remove: function (key) {
            try { window.localStorage.removeItem(key); } catch (error) { /* navigation privée */ }
        }
    };

    function status(text, tone) {
        statusEls.forEach(function (el) {
            el.textContent = text;
            el.setAttribute('data-tone', tone || 'info');
        });
    }

    var supported = 'serviceWorker' in navigator && 'Notification' in window && 'PushManager' in window
        && typeof firebase !== 'undefined' && config.apiKey && vapidKey && window.isSecureContext;
    if (!supported) {
        status(window.isSecureContext
            ? 'Ce navigateur ne gère pas les notifications. Essayez Chrome, Edge ou Firefox (sur iPhone : ajoutez SESAM à l\'écran d\'accueil).'
            : 'Les notifications demandent une connexion sécurisée (https).', 'warning');
        document.querySelectorAll('[data-push-enable]').forEach(function (button) { button.disabled = true; });
        return;
    }

    firebase.initializeApp(config);
    var messaging = firebase.messaging();
    var registrationPromise = null;

    function serviceWorker() {
        if (!registrationPromise) {
            registrationPromise = navigator.serviceWorker.register(swUrl, { scope: scope });
        }
        return registrationPromise;
    }

    function post(url, data) {
        var body = new URLSearchParams(data);
        body.set('_csrf', csrf);
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            body: body
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        });
    }

    function subscribe() {
        return serviceWorker().then(function (registration) {
            return messaging.getToken({ vapidKey: vapidKey, serviceWorkerRegistration: registration });
        }).then(function (token) {
            if (!token) {
                throw new Error('Aucun jeton');
            }
            return post(registerUrl, { token: token }).then(function () {
                storage.set('sesam-push-token', token);
                storage.set('sesam-push-sync', String(Date.now()));
                status('Notifications activées sur cet appareil.', 'success');
                return token;
            });
        });
    }

    function enable() {
        status('Activation en cours…', 'info');
        return Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                status('Notifications refusées. Pour les recevoir, autorisez-les dans les réglages du navigateur (icône à gauche de l\'adresse).', 'warning');
                storage.set('sesam-push-dismissed', String(Date.now()));
                hidePrompt();
                return null;
            }
            hidePrompt();
            return subscribe();
        }).catch(function () {
            status('Impossible d\'activer les notifications sur cet appareil pour le moment.', 'warning');
        });
    }

    function disable() {
        var token = storage.get('sesam-push-token');
        return messaging.deleteToken().catch(function () { return false; }).then(function () {
            storage.remove('sesam-push-token');
            if (token) {
                return post(unregisterUrl, { token: token });
            }
            return null;
        }).then(function () {
            status('Notifications désactivées sur cet appareil.', 'info');
        });
    }

    function hidePrompt() {
        if (prompt) {
            prompt.hidden = true;
        }
    }

    document.querySelectorAll('[data-push-enable]').forEach(function (button) {
        button.addEventListener('click', function () {
            button.disabled = true;
            enable().then(function () { button.disabled = false; });
        });
    });
    document.querySelectorAll('[data-push-disable]').forEach(function (button) {
        button.addEventListener('click', function () {
            button.disabled = true;
            disable().then(function () { button.disabled = false; });
        });
    });
    document.querySelectorAll('[data-push-dismiss]').forEach(function (button) {
        button.addEventListener('click', function () {
            storage.set('sesam-push-dismissed', String(Date.now()));
            hidePrompt();
        });
    });

    if (Notification.permission === 'granted') {
        status('Notifications activées sur cet appareil.', 'success');
        // Jeton actualisé une fois par jour (Firebase le renouvelle parfois).
        var last = parseInt(storage.get('sesam-push-sync') || '0', 10);
        if (!storage.get('sesam-push-token') || Date.now() - last > 86400000) {
            subscribe().catch(function () { /* hors ligne : on réessaiera */ });
        } else {
            serviceWorker();
        }
    } else if (Notification.permission === 'default') {
        status('Les notifications ne sont pas encore activées sur cet appareil.', 'info');
        var dismissed = parseInt(storage.get('sesam-push-dismissed') || '0', 10);
        if (prompt && Date.now() - dismissed > 14 * 86400000) {
            prompt.hidden = false;
        }
    } else {
        status('Notifications bloquées dans ce navigateur. Autorisez-les dans ses réglages pour les recevoir.', 'warning');
    }

    // Application ouverte : bandeau dans la page et cloche mise à jour.
    messaging.onMessage(function (payload) {
        var data = (payload && payload.data) || {};
        var toast = document.createElement('a');
        toast.className = 'push-toast is-' + (data.level || 'info');
        toast.href = new URL((data.link || '/notifications').replace(/^\//, ''), new URL(scope, window.location.href)).href;
        var title = document.createElement('strong');
        title.textContent = data.title || 'SESAM';
        var body = document.createElement('span');
        body.textContent = data.body || '';
        toast.appendChild(title);
        toast.appendChild(body);
        document.body.appendChild(toast);
        window.setTimeout(function () { toast.classList.add('is-gone'); }, 7000);
        window.setTimeout(function () { toast.remove(); }, 7600);
        document.querySelectorAll('[data-notification-count]').forEach(function (badge) {
            var count = parseInt(badge.textContent || '0', 10) + 1;
            badge.textContent = String(count);
            badge.hidden = false;
        });
    });
}());
