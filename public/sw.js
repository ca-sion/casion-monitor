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
        badge: pushData.badge || '/favicon.png'
    };

    event.waitUntil(self.registration.showNotification(title, options));
});
