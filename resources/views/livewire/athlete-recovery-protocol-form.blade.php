<div class="container mx-auto max-w-4xl">
    <flux:heading size="xl" level="1">Ajouter une séance</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Physiothérapie, massage, récupération.</flux:text>

    @if ($injury)
        <flux:card class="flex flex-col gap-2" size="sm">
            <flux:text class="flex"><span class="w-24">Blessure :</span> {{ $injury->injury_type?->getPrefixForLocation() }} - {{ $injury->pain_location?->getLabel() }}</flux:text>
            <flux:text class="flex"><span class="w-24">Déclaration :</span> {{ $injury->declaration_date->format('d.m.Y') }}</flux:text>
            <flux:text class="flex"><span class="w-24">Statut :</span>
                <flux:badge size="sm"
                    inset="top bottom"
                    :color="$injury->status?->getColor()"
                    inset="top bottom">{{ $injury->status?->getLabel() ?? 'n/a' }}</flux:badge>
            </flux:text>
        </flux:card>
    @endif

    <section class="mt-6">
        <flux:callout icon="information-circle" variant="secondary">
            <flux:callout.heading>Information importante</flux:callout.heading>

            <flux:callout.text>
                <p>Ce formulaire vous permet de documenter les protocoles de récupération que vous suivez.</p>
                <ul class="ms-2 mt-2 list-inside list-disc space-y-1">
                    <li>Indiquez le type de récupération (repos, étirements, etc.).</li>
                    <li>Précisez la durée et ajoutez des notes si nécessaire.</li>
                    <li>Évaluez l'effet sur votre douleur et l'efficacité globale du protocole.</li>
                </ul>
            </flux:callout.text>
        </flux:callout>
    </section>
    <flux:separator class="my-8" variant="subtle" />

    <section class="mt-4">
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-4 flex items-center justify-between">
                @if ($injury)
                    <flux:button href="{{ route('athletes.injuries.show', ['hash' => $athlete->hash, 'injury' => $injury->id]) }}"
                        icon="arrow-left"
                        variant="outline">Retour</flux:button>
                @else
                    <flux:button href="{{ route('athletes.dashboard', ['hash' => $athlete->hash]) }}"
                        icon="arrow-left"
                        variant="outline">Retour</flux:button>
                @endif

                <flux:button type="submit"
                    icon="check"
                    variant="primary">Sauvegarder</flux:button>
            </div>
        </form>
    </section>
</div>
