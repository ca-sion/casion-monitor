<x-slot:title>{{ $athlete->name }} - Formulaire quotidien</x-slot>
<div class="mx-auto max-w-sm">

    <flux:heading size="xl" level="1">{{ $athlete->name }}</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Rentrer les métriques pour le jour choisi.</flux:text>
    <form wire:submit="save">
        {{ $this->form }}

        <flux:button class="mt-4"
            type="submit"
            variant="primary">Déclarer la blessure</flux:button>
    </form>

    <x-filament-actions::modals />
</div>