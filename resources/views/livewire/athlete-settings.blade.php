<div class="">
    <h1 class="mb-4 text-2xl font-semibold">Paramètres</h1>

    <div class="mb-6">
        <form wire:submit.prevent="savePreferences">
            {{ $this->preferencesForm }}

            <div class="mt-4">
                <x-filament::button type="submit">
                    Sauvegarder
                </x-filament::button>
            </div>
        </form>
    </div>

    <div class="mb-6">
        <h3 class="mb-2 text-lg font-medium">Rappels quotidiens</h3>
        <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
            Configurez jusqu'à cing rappels par jour pour vous aider à rester à jour.
        </p>

        {{ $this->table }}
    </div>

    <div class="mb-6">
        <h2 class="mb-2 text-lg font-medium">Notifications</h2>
        <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
            Recevez des notifications directement sur vos appareils.
        </p>

        <h3 class="mb-2 font-medium">Telegram</h3>
        <div class="mb-6 rounded-lg border bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            @if ($telegramChatId)
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-semibold text-green-600 dark:text-green-400">Votre compte est lié à Telegram.</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Vous recevrez les notifications via le bot <strong>{{ config('services.telegram-bot-api.username') }}</strong>.</p>
                    </div>
                    <x-filament::button color="danger"
                        outlined="true"
                        wire:click="unlinkTelegram">
                        Dissocier
                    </x-filament::button>
                </div>
            @else
                @if ($telegramActivationUrl)
                    <div wire:poll.5s="checkTelegramActivation">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start">
                            <!-- QR Code and Link -->
                            <div class="flex flex-col items-center gap-2"
                                x-data="{}"
                                x-init="new QRCode($refs.qrcode, {
                                    text: '{{ $telegramActivationUrl }}',
                                    width: 128,
                                    height: 128,
                                    colorDark: '#000000',
                                    colorLight: '#ffffff',
                                    correctLevel: QRCode.CorrectLevel.H
                                });"
                                wire:ignore>
                                <div class="rounded-lg bg-white p-2" x-ref="qrcode"></div>
                                <a class="fi-btn fi-btn-size-md fi-btn-color-primary fi-btn-variant-solid dark:fi-btn-color-primary"
                                    href="{{ $telegramActivationUrl }}"
                                    target="_blank">
                                    Ouvrir le lien
                                </a>
                            </div>

                            <!-- Instructions -->
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800 dark:text-gray-200">Liez votre compte Telegram en 2 étapes :</p>
                                <ol class="mt-2 list-inside list-decimal space-y-2 text-sm text-gray-600 dark:text-gray-400">
                                    <li>
                                        <strong>Scannez le QR code</strong> avec votre téléphone ou <strong>cliquez sur le lien</strong>.
                                    </li>
                                    <li>
                                        Dans Telegram, appuyez sur le bouton <strong>"Démarrer"</strong> qui apparaît.
                                    </li>
                                </ol>
                                <div class="bg-primary-50 dark:bg-primary-500/10 text-primary-700 dark:text-primary-400 mt-4 flex items-center gap-3 rounded-lg p-3">
                                    <x-filament::loading-indicator class="h-5 w-5" />
                                    <flux:text>En attente d'activation... Nous détectons la liaison automatiquement.</flux:text>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 border-t pt-4 text-xs text-gray-500 dark:border-gray-700/50 dark:text-gray-400">
                            <p>Si la liaison automatique ne fonctionne pas :</p>
                            <ul class="mt-1 list-inside list-disc">
                                <li>Contrôler d'avoir <a class="underline"
                                        href="https://telegram.org/"
                                        target="_blank">installé l'application Telegram</a>.</li>
                                <li>Assurez-vous d'avoir bien cliqué sur "Démarrer" dans Telegram.</li>
                                <li>Vous pouvez forcer une {{ $this->scanForTelegramChatIdAction }}.</li>
                                <li>En dernier recours, vous pouvez {{ $this->linkTelegramManuallyAction }}.</li>
                            </ul>
                        </div>
                    </div>
                    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
                @else
                    <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
                        Le service de liaison Telegram est actuellement indisponible. Nous n'avons pas pu générer de lien d'activation.
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Vous pouvez tenter une liaison manuelle : {{ $this->linkTelegramManuallyAction }}
                    </p>
                @endif
            @endif
        </div>

        <h3 class="mb-2 font-medium">Web push</h3>
        <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
            Notifications PWA (ne fonctionnent pas lorsque l'application est en arrière-plan).
        </p>

        <button class="fi-btn fi-btn-size-md fi-btn-color-gray fi-btn-variant-outline dark:fi-btn-color-gray"
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
            :class="$wire.isSubscribed ? 'bg-red-500 text-white hover:bg-red-600' : 'bg-green-500 text-white hover:bg-green-600'">
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

    <div class="mb-6 border-t pt-6 dark:border-gray-700">
        <h2 class="mb-2 text-lg font-medium text-red-600 dark:text-red-400">Développement</h2>
        <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
            Cette section contient des outils de débogage et de test.
        </p>
        <x-filament::button type="button"
            color="warning"
            x-on:click="
                localStorage.removeItem('pwaInstallDismissed');
                alert('Préférence de la bannière PWA réinitialisée. Elle se réaffichera au prochain chargement de page.');
            ">
            Réafficher la bannière d'installation PWA
        </x-filament::button>
    </div>

    <x-filament-actions::modals />
</div>
