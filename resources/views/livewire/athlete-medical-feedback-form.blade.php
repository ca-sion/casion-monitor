<div class="container mx-auto max-w-4xl">
    <flux:heading size="xl" level="1">Ajouter un feedback médical</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Pendant ou suite à une consultation.</flux:text>
    <flux:card class="flex flex-col gap-2" size="sm">
        <flux:text class="flex"><span class="w-24">Blessure :</span> {{ $injury->type }} - {{ $injury->location }}</flux:text>
        <flux:text class="flex"><span class="w-24">Déclaration :</span> {{ $injury->declaration_date->format('d/m/Y') }}</flux:text>
        <flux:text class="flex"><span class="w-24">Statut :</span>
            <flux:badge size="sm"
                inset="top bottom"
                :color="$injury->status?->getColor()"
                inset="top bottom">{{ $injury->status?->getLabel() ?? 'N/A' }}</flux:badge>
        </flux:text>
    </flux:card>
    <section class="mt-6">
        <flux:callout icon="information-circle" variant="secondary">
            <flux:callout.heading>Information importante</flux:callout.heading>

            <flux:callout.text>
                <p>Ce formulaire vous permet de documenter les informations reçues lors de votre consultation médicale. Votre entraîneur pourra ensuite compléter ou modifier ces informations si nécessaire.</p>
                <ul class="ms-2 mt-2 list-inside list-disc space-y-1">
                    <li>Remplissez les champs avec les informations communiquées par le professionnel de santé,</li>
                    <li>Tous les champs sont optionnels, mais plus vous fournirez d'informations, mieux votre entraîneur pourra adapter votre programme.</li>
                    <li>Vous pouvez ajouter plusieurs feedbacks pour la même blessure si vous consultez plusieurs fois.</li>
                </ul>
            </flux:callout.text>
        </flux:callout>
    </section>
    <flux:separator class="my-8" variant="subtle" />

    <section class="mt-4">
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-4 flex items-center justify-between">
                <flux:button href="{{ route('athletes.injuries.show', ['hash' => $athlete->hash, 'injury' => $injury->id]) }}"
                    icon="arrow-left"
                    variant="outline">Retour</flux:button>

                <flux:button type="submit"
                    icon="check"
                    variant="primary">Sauvegarder</flux:button>
            </div>
        </form>
    </section>
</div>
