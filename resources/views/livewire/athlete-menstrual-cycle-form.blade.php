<x-slot:title>{{ $athlete->name }} - Cycles menstruels</x-slot>
<div class="mx-auto max-w-2xl">

    <flux:heading size="xl" level="1">{{ $athlete->name }}</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Rentrer les premiers jours de vos cycle menstruels.</flux:text>

    <form wire:submit="save">
        {{ $this->form }}

        <flux:button class="mt-4"
            type="submit"
            variant="primary">Sauvegarder</flux:button>
    </form>

    <x-filament-actions::modals />
</div>
