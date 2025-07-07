<x-slot:title>{{ $athlete->name }} - Formulaire mensuel</x-slot>
<div class="mx-auto max-w-2xl">

    <flux:heading size="xl" level="1">{{ $athlete->name }}</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Rentrer les métriques pour le mois en cours.</flux:text>

    <form wire:submit="save">
        {{ $this->form }}

        <flux:button class="mt-4"
            type="submit"
            variant="primary">Sauvegarder</flux:button>
    </form>

    <x-filament-actions::modals />
</div>
