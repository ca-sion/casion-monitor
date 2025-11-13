<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen dark:bg-zinc-800">

        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('athletes.dashboard', ['hash' => auth('athlete')->user()->hash]) }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group heading="Suivi des entraînements" class="grid">
                    <flux:navlist.item icon="home" :href="route('athletes.dashboard', ['hash' => auth('athlete')->user()->hash])" :current="request()->routeIs('athletes.dashboard')" wire:navigate>Tableau de bord</flux:navlist.item>
                    <flux:navlist.item icon="book-open" :href="route('athletes.journal', ['hash' => auth('athlete')->user()->hash])" :current="request()->routeIs('athletes.journal')" wire:navigate>Journal</flux:navlist.item>
                    <flux:navlist.item icon="clipboard-document-check" :href="route('athletes.reports.show', ['hash' => auth('athlete')->user()->hash])" :current="request()->routeIs('athletes.reports.show')" wire:navigate>Rapport</flux:navlist.item>
                    <flux:navlist.item icon="plus" :href="route('athletes.metrics.daily.form', ['hash' => auth('athlete')->user()->hash])" :current="request()->routeIs('athletes.metrics.daily.form')" wire:navigate>Quotidien</flux:navlist.item>
                    <flux:navlist.item icon="plus" :href="route('athletes.metrics.monthly.form', ['hash' => auth('athlete')->user()->hash])" :current="request()->routeIs('athletes.metrics.monthly.form')" wire:navigate>Mensuel</flux:navlist.item>
                    <flux:navlist.item icon="chart-bar-square" :href="route('athletes.statistics', ['hash' => auth('athlete')->user()->hash])" :current="request()->routeIs('athletes.statistics')" wire:navigate>Statistiques</flux:navlist.item>
                </flux:navlist.group>
                <flux:navlist.group heading="Suivi médical" class="grid">
                    <flux:navlist.item icon="clipboard-document-list" :href="route('athletes.injuries.index', ['hash' => auth('athlete')->user()->hash])" :current="request()->routeIs('athletes.injuries.index')" wire:navigate>Tableau de bord</flux:navlist.item>
                    <flux:navlist.item icon="plus" :href="route('athletes.health-events.create', ['hash' => auth('athlete')->user()->hash])" :current="request()->routeIs('athletes.health-events.create')" wire:navigate>Séance</flux:navlist.item>
                    {{--
                    <flux:navlist.item icon="plus" :href="route('athletes.injuries.create', ['hash' => auth('athlete')->user()->hash])" :current="request()->routeIs('athletes.injuries.create')" wire:navigate>Douleur/blessure</flux:navlist.item>
                    --}}
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <flux:navlist variant="outline">
                <flux:navlist.item icon="book-open-text" href="https://casion.ch" target="_blank">
                casion.ch
                </flux:navlist.item>
            </flux:navlist>

            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    :name="auth('athlete')->user()?->name"
                    :initials="auth('athlete')->user()?->initials"
                    icon:trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth('athlete')->user()?->initials }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth('athlete')->user()?->name }}</span>
                                    <span class="truncate text-xs">{{ auth('athlete')->user()?->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('athletes.settings', ['hash' => auth('athlete')->user()?->hash])" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth('athlete')->user()?->initials"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth('athlete')->user()?->initials }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth('athlete')->user()?->name }}</span>
                                    <span class="truncate text-xs">{{ auth('athlete')->user()?->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('athletes.settings', ['hash' => auth('athlete')->user()?->hash])" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        <x-pwa-install-banner />
        @livewire('notifications')
        @fluxScripts
        @filamentScripts
        @vite('resources/js/app.js')
    </body>
</html>
