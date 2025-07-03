<x-layouts.athlete :title="$athlete->name">
    <flux:heading size="xl" level="1">Bonjour, {{ $athlete->first_name }}</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Voici ton tableau de bord.</flux:text>

    <flux:separator variant="subtle" />

    <a href="{{ route('athletes.go.metrics.form', ['hash' => $athlete->hash]) }}" aria-label="Ajouter une métrique">
        <flux:card size="sm" class="my-4 hover:bg-zinc-50 dark:hover:bg-zinc-700 bg-lime-50! border-lime-400!" color="lime">
            <flux:heading class="flex items-center gap-2">Ajouter une métrique <flux:icon name="arrow-up-right" class="ml-auto text-lime-600" variant="micro" /></flux:heading>
            <flux:text class="mt-2">Vous pouvez ajouter une nouvelle métrique aujourd'hui. Ou alors sélectionner une autre date.</flux:text>
        </flux:card>
    </a>

    <flux:table class="my-4">
    <flux:table.columns>
        <flux:table.column> </flux:table.column>
        <flux:table.column>Date</flux:table.column>
        <flux:table.column>Jour</flux:table.column>
        <flux:table.column>Métriques</flux:table.column>
    </flux:table.columns>

    <flux:table.rows>
        @foreach ($athlete->metricsByDates as $date => $metricDates)
        <flux:table.row>
            <flux:table.cell><flux:link href="{{ route('athletes.go.metrics.form', ['hash' => $athlete->hash, 'd' => $metricDates->first()->date->format('Y-m-d')]) }}">Modifier</flux:link></flux:table.cell>
            <flux:table.cell>{{ $metricDates->first()->date->locale('fr_CH')->isoFormat('L') }}</flux:table.cell>
            <flux:table.cell>{{ $metricDates->first()->date->locale('fr_CH')->isoFormat('dddd') }}</flux:table.cell>
            <flux:table.cell>
                {{ count($metricDates) }}
            </flux:table.cell>
        </flux:table.row> 
        @endforeach
    </flux:table.rows>
</flux:table>


</x-layouts.athlete>
