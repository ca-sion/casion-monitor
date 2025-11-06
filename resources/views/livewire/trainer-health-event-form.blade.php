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

    @if ($healthEvent && $healthEvent->reported_by_athlete)
    <section class="mt-6">
        <flux:callout icon="information-circle" color="blue">
            <flux:callout.heading>Ce feedback a été initialement créé par l'athlète. Vous pouvez le compléter ou le modifier.</flux:callout.heading>
        </flux:callout>
    </section>
    @endif

    <section class="mt-6">
        <flux:callout icon="information-circle" variant="secondary">
            <flux:callout.heading>Conseils pour l'édition</flux:callout.heading>

            <flux:callout.text>
                <p>Ce formulaire vous permet de modifier les informations reçues par l'athlète lors de la consultation médicale.</p>
                <ul class="ms-2 mt-2 list-inside list-disc space-y-1">
                    <li>Complétez les informations manquantes basées sur votre expertise.</li>
                    <li>Ajustez les limitations d'entraînement selon votre programme.</li>
                    <li>Précisez le plan de traitement si nécessaire.</li>
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
