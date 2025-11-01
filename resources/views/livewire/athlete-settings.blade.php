<div class="p-4">
    <h2 class="text-xl font-semibold mb-4">Paramètres</h2>

    <div class="mb-6">
        <h3 class="text-lg font-medium mb-2">Notifications Push</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            Recevez des rappels directement sur votre appareil pour ne pas oublier de remplir votre monitoring.
        </p>

        <button
            x-data="{}"
            x-on:click="
                if (!('serviceWorker' in navigator)) {
                    alert('Votre navigateur ne supporte pas les Service Workers.');
                    return;
                }
                if (!('PushManager' in window)) {
                    alert('Votre navigateur ne supporte pas les Notifications Push.');
                    return;
                }

                if ($wire.isSubscribed) {
                    navigator.serviceWorker.ready.then(registration => {
                        registration.pushManager.getSubscription().then(subscription => {
                            if (subscription) {
                                subscription.unsubscribe().then(successful => {
                                    if (successful) {
                                        $wire.unsubscribe(subscription.endpoint);
                                    } else {
                                        console.error('Failed to unsubscribe from browser push service.');
                                        alert('Impossible de se désabonner du service de notification du navigateur.');
                                    }
                                }).catch(error => {
                                    console.error('Erreur lors de la désinscription du service de notification du navigateur:', error);
                                    alert('Erreur lors de la désinscription du service de notification du navigateur.');
                                });
                            } else {
                                // If no subscription found in browser, but backend thinks it's subscribed (isSubscribed is true),
                                // we still need to tell the backend to clear its state for this user.
                                // We pass an empty string, and the backend will handle it (it won't find a record to delete).
                                $wire.unsubscribe('');
                            }
                        }).catch(error => {
                            console.error('Erreur lors de la récupération de l\'abonnement pour désinscription:', error);
                            alert('Impossible de récupérer l\'abonnement pour désactiver les notifications.');
                        });
                    });
                } else {
                    Notification.requestPermission().then(permission => {
                        if (permission === 'granted') {
                            navigator.serviceWorker.ready.then(registration => {
                                registration.pushManager.subscribe({
                                    userVisibleOnly: true,
                                    applicationServerKey: urlBase64ToUint8Array($wire.vapidPublicKey)
                                }).then(subscription => {
                                    $wire.subscribe(subscription.toJSON());
                                }).catch(error => {
                                    console.error('Erreur d\'abonnement push:', error);
                                    alert('Impossible de s\'abonner aux notifications push. Vérifiez vos paramètres de navigateur.');
                                });
                            });
                        } else {
                            alert('Permission de notification refusée.');
                        }
                    });
                }
            "
            class="fi-btn fi-btn-size-md fi-btn-color-gray fi-btn-variant-outline dark:fi-btn-color-gray"
            :class="$wire.isSubscribed ? 'bg-red-500 text-white hover:bg-red-600' : 'bg-green-500 text-white hover:bg-green-600'"
        >
            <span class="fi-btn-label">
                <span x-show="$wire.isSubscribed">
                    Désactiver les notifications
                </span>
                <span x-show="!$wire.isSubscribed">
                    Activer les notifications
                </span>
            </span>
        </button>
    </div>

    <div class="mb-6">
        <h3 class="text-lg font-medium mb-2">Rappels quotidiens</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            Configurez jusqu'à cing rappels par jour pour vous aider à rester à jour.
        </p>

        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</div>