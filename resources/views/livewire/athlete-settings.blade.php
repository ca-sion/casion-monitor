<div class="p-4">
    <h1 class="text-2xl font-semibold mb-4">Paramètres</h1>

    <div class="mb-6">
        <h2 class="text-lg font-medium mb-2">Notifications</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            Recevez des notifications directement sur vos appareils.
        </p>

        <h3 class="font-medium mb-2">Telegram</h3>
        <div class="p-4 mb-6 border dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800/50">
            @if ($telegramChatId)
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-green-600 dark:text-green-400">Votre compte est lié à Telegram.</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Vous recevrez les notifications via le bot <strong>{{ config('services.telegram-bot-api.username') }}</strong>.</p>
                    </div>
                    <x-filament::button color="danger" outlined="true" wire:click="unlinkTelegram">
                        Dissocier
                    </x-filament::button>
                </div>
            @else
                @if ($telegramActivationUrl)
                    <div wire:poll.5s="checkTelegramActivation">
                        <div class="flex flex-col md:flex-row md:items-start gap-4">
                            <!-- QR Code and Link -->
                            <div x-data="{}" x-init="
                                new QRCode($refs.qrcode, {
                                    text: '{{ $telegramActivationUrl }}',
                                    width: 128,
                                    height: 128,
                                    colorDark: '#000000',
                                    colorLight: '#ffffff',
                                    correctLevel: QRCode.CorrectLevel.H
                                });
                            " class="flex flex-col items-center gap-2" wire:ignore>
                                <div x-ref="qrcode" class="p-2 bg-white rounded-lg"></div>
                                <a href="{{ $telegramActivationUrl }}" target="_blank" class="fi-btn fi-btn-size-md fi-btn-color-primary fi-btn-variant-solid dark:fi-btn-color-primary">
                                    Ouvrir le lien
                                </a>
                            </div>
                            
                            <!-- Instructions -->
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800 dark:text-gray-200">Liez votre compte Telegram en 2 étapes :</p>
                                <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-400 mt-2 space-y-2">
                                    <li>
                                        <strong>Scannez le QR code</strong> avec votre téléphone ou <strong>cliquez sur le lien</strong>.
                                    </li>
                                    <li>
                                        Dans Telegram, appuyez sur le bouton <strong>"Démarrer"</strong> qui apparaît.
                                    </li>
                                </ol>
                                <div class="mt-4 p-3 rounded-lg bg-primary-50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-400 flex items-center gap-3">
                                    <x-filament::loading-indicator class="h-5 w-5" />
                                    <flux:text>En attente d'activation... Nous détectons la liaison automatiquement.</flux:text>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t dark:border-gray-700/50 text-xs text-gray-500 dark:text-gray-400">
                            <p>Si la liaison automatique ne fonctionne pas :</p>
                            <ul class="list-disc list-inside mt-1">
                                <li>Contrôler d'avoir <a href="https://telegram.org/" class="underline" target="_blank">installé l'application Telegram</a>.</li>
                                <li>Assurez-vous d'avoir bien cliqué sur "Démarrer" dans Telegram.</li>
                                <li>Vous pouvez forcer une {{ $this->scanForTelegramChatIdAction }}.</li>
                                <li>En dernier recours, vous pouvez {{ $this->linkTelegramManuallyAction }}.</li>
                            </ul>
                        </div>
                    </div>
                    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                        Le service de liaison Telegram est actuellement indisponible. Nous n'avons pas pu générer de lien d'activation.
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Vous pouvez tenter une liaison manuelle : {{ $this->linkTelegramManuallyAction }}
                    </p>
                @endif
            @endif
        </div>

        <h3 class="font-medium mb-2">Web push</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            Notifications PWA (ne fonctionnent pas lorsque l'application est en arrière-plan).
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
        <h3 class="text-lg font-medium mb-2">Installer l'application (PWA)</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            Pour un accès plus rapide et une expérience similaire à une application native, vous pouvez installer notre PWA (Progressive Web App) sur votre smartphone.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 border dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800/50">
            <div>
                <h4 class="font-semibold text-gray-800 dark:text-gray-200">Sur Android (avec Chrome)</h4>
                <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                    <li>Appuyez sur le bouton de menu (les trois points verticaux) en haut à droite.</li>
                    <li>Sélectionnez <strong>"Installer l'application"</strong> ou <strong>"Ajouter à l'écran d'accueil"</strong>.</li>
                    <li>Suivez les instructions pour confirmer.</li>
                </ol>
            </div>
            <div>
                <h4 class="font-semibold text-gray-800 dark:text-gray-200">Sur iOS (avec Safari)</h4>
                <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                    <li>Appuyez sur l'icône de partage (le carré avec une flèche vers le haut) dans la barre de menu.</li>
                    <li>Faites défiler vers le bas et sélectionnez <strong>"Sur l'écran d'accueil"</strong>.</li>
                    <li>Appuyez sur <strong>"Ajouter"</strong> en haut à droite.</li>
                </ol>
            </div>
        </div>
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