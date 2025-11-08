@props(['isIOS', 'isAndroid'])

<div x-data="pwaInstallBanner()" x-init="init()" x-show="showBanner"
    class="fixed bottom-4 right-4 z-50 max-w-sm rounded-lg bg-white ml-4 p-4 shadow-lg ring-1 ring-gray-900/10 dark:bg-gray-800"
    style="display: none;">
    <div class="flex items-start">
        <div class="mr-4 flex-shrink-0">
            <img src="{{ asset('apple-touch-icon.png') }}" alt="App Icon" class="h-12 w-12 rounded-md">
        </div>
        <div class="flex-1">
            <p class="font-bold text-gray-900 dark:text-white">Installer l'application</p>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Accédez plus rapidement à votre suivi en installant l'application sur votre appareil.
            </p>

            {{-- Instructions pour iOS --}}
            <div x-show="isIos" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                <ol class="list-decimal list-inside space-y-1">
                    <li>Depuis le navigateur Safari, appuyez sur l'icône de partage
                    <x-heroicon-s-arrow-up-on-square class="inline h-4 w-4" /> généralement située en bas au centre de l'écran.</li>
                    <li>Dans le menu de partage qui apparaît, faites défiler vers le bas et sélectionnez l'option <em class="font-bold">Ajouter à l'écran d'accueil</em>.</li>
                    <p>Vous devrez peut-être balayer vers la gauche pour trouver cette option.</p>
                </ol>
            </div>
        </div>
        <div class="ml-4 flex-shrink-0">
            <button @click="dismiss()" type="button"
                class="-m-1 inline-flex rounded-md p-1 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:text-gray-500 dark:hover:text-gray-400">
                <x-heroicon-s-x-mark class="h-5 w-5" />
            </button>
        </div>
    </div>
    <div class="mt-4 flex justify-end">
        {{-- Bouton d'installation pour Android/Desktop --}}
        <button x-show="!isIos" @click="install()" type="button"
            class="fi-btn fi-btn-size-md fi-btn-color-primary fi-btn-variant-solid dark:fi-btn-color-primary">
            Installer
        </button>
    </div>
</div>

<script>
    function pwaInstallBanner() {
        return {
            showBanner: false,
            isIos: false,
            installPrompt: null,

            init() {
                // 1. Ne rien afficher si l'utilisateur a déjà fermé la bannière
                if (localStorage.getItem('pwaInstallDismissed') === 'true') {
                    return;
                }

                // 2. Ne rien afficher si l'app est déjà en mode standalone
                if (window.matchMedia('(display-mode: standalone)').matches) {
                    return;
                }

                // 3. Détecter l'OS
                this.isIos = /iPhone|iPad|iPod/.test(navigator.userAgent);

                if (this.isIos) {
                    // Sur iOS, on affiche simplement la bannière d'instructions
                    this.showBanner = true;
                } else {
                    // Pour les autres (Android/Desktop), on attend l'événement d'installation
                    window.addEventListener('beforeinstallprompt', (e) => {
                        e.preventDefault();
                        this.installPrompt = e;
                        this.showBanner = true;
                    });
                }
            },

            install() {
                if (!this.installPrompt) {
                    return;
                }
                this.installPrompt.prompt();
                // On ne cache pas la bannière ici, on attend le choix de l'utilisateur
                this.installPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        // L'utilisateur a installé la PWA
                        this.dismiss();
                    }
                });
            },

            dismiss() {
                localStorage.setItem('pwaInstallDismissed', 'true');
                this.showBanner = false;
            }
        }
    }
</script>
