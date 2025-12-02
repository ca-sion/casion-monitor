<x-slot:title>Déclarer une blessure</x-slot>
<div class="mx-auto max-w-sm">

    <flux:heading size="xl" level="1">Déclarer une blessure</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Déclarer une blessure pour un de vos athlètes.</flux:text>
    <form wire:submit="save">
        {{ $this->form }}

        <flux:button class="mt-4"
            type="submit"
            variant="primary">Déclarer la blessure</flux:button>
    </form>

    <x-filament-actions::modals />
</div>