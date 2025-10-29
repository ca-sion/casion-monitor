self.addEventListener('install', function(event) {
    console.log('Service Worker installing.');
});

self.addEventListener('activate', function(event) {
    console.log('Service Worker activating.');
});

self.addEventListener('push', function(event) {
    console.log('[Service Worker] Push Received.');
    const pushData = event.data.json();

    const title = pushData.title || 'Casion Monitor';
    const options = {
        body: pushData.body || 'Vous avez une nouvelle notification.',
        icon: pushData.icon || '/favicon.png',
        badge: pushData.badge || '/favicon.png',
        data: pushData.data || {} // Pass data from push to notification
    };

    if (pushData.actions) {
        options.actions = pushData.actions;
    }

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    console.log('[Service Worker] Notification click Received.');

    event.notification.close();

    // Default URL if none is provided
    const urlToOpen = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then(function(clientList) {
            // Check if a window with the same URL is already open.
            for (const client of clientList) {
                // Use URL objects to compare paths, ignoring query params or hash
                const clientUrl = new URL(client.url);
                const targetUrl = new URL(urlToOpen, self.location.origin);

                if (clientUrl.pathname === targetUrl.pathname && 'focus' in client) {
                    return client.focus();
                }
            }
            // If not, open a new window.
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});