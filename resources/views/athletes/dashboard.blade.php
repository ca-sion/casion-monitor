<x-layouts.athlete :title="$athlete->name">
    <flux:heading size="xl" level="1">Bonjour, {{ $athlete->first_name }}</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Voici ton tableau de bord.</flux:text>

    <flux:separator variant="subtle" />

    <a href="{{ route('athletes.metrics.daily.form', ['hash' => $athlete->hash]) }}" aria-label="Ajouter une métrique">
        <flux:card class="bg-lime-50! border-lime-400! my-4 hover:bg-zinc-50 dark:hover:bg-zinc-700"
            size="sm"
            color="lime">
            <flux:heading class="flex items-center gap-2">Ajouter des métriques quotidienne
                <flux:icon class="ml-auto text-lime-600"
                    name="plus"
                    variant="micro" />
            </flux:heading>
            <flux:text class="mt-2">Vous pouvez ajouter de nouvelles métriques quotidiennes pour aujourd'hui. Ou sélectionner un autre jour.</flux:text>
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
                    <flux:table.cell>
                        <flux:link href="{{ $metricDates->first()->data->edit_link }}">Modifier</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $metricDates->first()->data->formatted_date }}</flux:table.cell>
                    <flux:table.cell>{{ $metricDates->first()->data->day }}</flux:table.cell>
                    <flux:table.cell>
                        {{ count($metricDates) }}
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

</x-layouts.athlete>
