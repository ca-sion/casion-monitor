<div class="container mx-auto max-w-4xl">
    <flux:heading size="xl" level="1">Modifier un feedback médical</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Suite à une consultation de l'athlète.</flux:text>
    <flux:card class="flex flex-col gap-2" size="sm">
        <flux:text class="flex"><span class="w-24">Athlète :</span> {{ $medicalFeedback->injury->athlete->name }}</flux:text>
        <flux:text class="flex"><span class="w-24">Blessure :</span> {{ $medicalFeedback->injury->injury_type?->getPrefixForLocation() }} - {{ $medicalFeedback->injury->pain_location?->getLabel() }}</flux:text>
        <flux:text class="flex"><span class="w-24">Déclaration :</span> {{ $medicalFeedback->injury->declaration_date->format('d.m.Y') }}</flux:text>
        <flux:text class="flex"><span class="w-24">Statut :</span>
            <flux:badge size="sm"
                inset="top bottom"
                :color="$medicalFeedback->injury->status?->getColor()"
                inset="top bottom">{{ $medicalFeedback->injury->status?->getLabel() ?? 'n/a' }}</flux:badge>
        </flux:text>
    </flux:card>
    @if ($medicalFeedback->reported_by_athlete)
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
                    <li>Vos modifications seront enregistrées et associées à votre nom.</li>
                </ul>
            </flux:callout.text>
        </flux:callout>
    </section>
    <flux:separator class="my-8" variant="subtle" />

    <section class="mt-4">
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-4 flex items-center justify-between">
                <flux:button href="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete' => $medicalFeedback->injury->athlete_id]) }}"
                    icon="arrow-left"
                    variant="outline">Retour à l'athlète</flux:button>

                <flux:button type="submit"
                    icon="check"
                    variant="primary">Mettre à jour le feedback</flux:button>
            </div>

        </form>
    </section>
