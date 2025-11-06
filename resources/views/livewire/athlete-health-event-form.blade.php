<div class="container mx-auto max-w-4xl">
    <flux:heading size="xl" level="1">Ajouter/Modifier une séance</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Médecin, physiothérapie, coaching, massage, récupération, etc.</flux:text>

    @if ($injury)
        <flux:card class="text-sm" size="sm">
            <table>
                <tr>
                    <td class="w-24">Blessure :</td>
                    <td>{{ $injury->injury_type?->getPrefixForLocation() }} - {{ $injury->pain_location?->getLabel() }}</td>
                </tr>
                <tr>
                    <td class="w-24">Déclaration :</td>
                    <td>{{ $injury->declaration_date->format('d.m.Y') }}</td>
                </tr>
                <tr>
                    <td class="w-24">Statut :</td>
                    <td>
                        <flux:badge size="sm"
                            :color="$injury->status?->getColor()"
                            inset="top bottom">{{ $injury->status?->getLabel() ?? 'n/a' }}</flux:badge>
                    </td>
                </tr>
            </table>
        </flux:card>
    @endif

    <section class="mt-6">
        <flux:callout icon="information-circle" variant="secondary">
            <flux:callout.heading>Information importante</flux:callout.heading>

            <flux:callout.text>
                <p>Ce formulaire vous permet de documenter les séances que vous avez.</p>
                <ul class="ms-2 mt-2 list-inside list-disc space-y-1">
                    <li>Indiquez le type de séance.</li>
                    <li>Remplissez les champs avec les informations communiquées par le professionnel de santé.</li>
                    <li>Précisez la durée et ajoutez des notes si nécessaire.</li>
                    <li>Tous les champs sont optionnels, mais plus vous fournirez d'informations, mieux votre entraîneur pourra adapter votre programme.</li>
                </ul>
            </flux:callout.text>
        </flux:callout>
    </section>
    <flux:separator class="my-8" variant="subtle" />

    <section class="mt-4">

        <form wire:submit.prevent="save">
            {{ $this->form }}

            <div class="mt-6">
                <div class="mt-4 flex items-center justify-between">
                    <flux:button type="submit"
                        icon="check"
                        variant="primary">Sauvegarder</flux:button>
                </div>
            </div>
        </form>

        <x-filament-actions::modals />
    </section>
</div>
