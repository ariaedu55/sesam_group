/* Service worker SESAM : notifications push Firebase quand l'application est fermée ou en arrière-plan. */
/* global firebase, importScripts, clients */
importScripts('__FIREBASE_APP__', '__FIREBASE_MESSAGING__');

firebase.initializeApp(__FIREBASE_CONFIG__);
var messaging = firebase.messaging();

// Les messages sont envoyés en « data » : le service worker compose lui-même la notification.
messaging.onBackgroundMessage(function (payload) {
    var data = (payload && payload.data) || {};
    return self.registration.showNotification(data.title || 'SESAM', {
        body: data.body || '',
        icon: '__ICON__',
        badge: '__ICON__',
        tag: data.tag || undefined,
        renotify: true,
        data: { link: data.link || '/notifications' }
    });
});

// Clic : on revient sur l'onglet SESAM déjà ouvert, sinon on l'ouvre, directement sur le bon écran.
self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var link = (event.notification.data && event.notification.data.link) || '/notifications';
    var target = new URL(link.replace(/^\//, ''), self.registration.scope).href;
    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
        for (var i = 0; i < windows.length; i++) {
            if (windows[i].url.indexOf(self.registration.scope) === 0 && 'focus' in windows[i]) {
                windows[i].navigate(target);
                return windows[i].focus();
            }
        }
        return clients.openWindow(target);
    }));
});

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(clients.claim());
});
