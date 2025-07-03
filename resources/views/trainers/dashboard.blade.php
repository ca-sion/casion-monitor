<x-layouts.trainer :title="$trainer->name">
    <flux:heading size="xl" level="1">Bonjour, {{ $trainer->first_name }}</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Voici ton tableau de bord.</flux:text>

    <flux:separator variant="subtle" />

    <flux:table class="my-4">
        <flux:table.columns>
            <flux:table.column> </flux:table.column>
            <flux:table.column>Athlète</flux:table.column>
            <flux:table.column>Dern. connection</flux:table.column>
            <flux:table.column>Dern. éval. fatigue post</flux:table.column>
            <flux:table.column>Dern. VFC/HRV</flux:table.column>
            <flux:table.column>VFC/HRV</flux:table.column>
            <flux:table.column>Eval. fatigue post</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($trainer->athletes as $athlete)
                <flux:table.row>
                    <flux:table.cell>
                        <flux:link href="{{ $athlete->accountLink }}">Compte</flux:link>
                        <flux:link href="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete' => $athlete->id]) }}">Métriques</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $athlete->name }}</flux:table.cell>
                    <flux:table.cell>{{ $athlete->last_connection?->locale('fr_CH')->isoFormat('L') }}</flux:table.cell>
                    <flux:table.cell>{{ $athlete->lastMetrics->first()?->where('metric_type', 'post_session_subjective_fatigue')?->first()?->value }}</flux:table.cell>
                    <flux:table.cell>{{ $athlete->lastMetrics->first()?->where('metric_type', 'morning_hrv')?->first()?->value }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:chart class="aspect-[3/1] w-full" :value="$athlete->metricsForChart->get('morning_hrv')->pluck('value')->take(14)">
                            <flux:chart.svg gutter="0">
                                <flux:chart.line class="text-blue-500 dark:text-blue-400" />
                            </flux:chart.svg>
                        </flux:chart>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:chart class="aspect-[3/1] w-full" :value="$athlete->metricsForChart->get('post_session_subjective_fatigue')->pluck('value')->take(14)">
                            <flux:chart.svg gutter="0">
                                <flux:chart.line class="text-zinc-500 dark:text-zinc-400" />
                            </flux:chart.svg>
                        </flux:chart>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

</x-layouts.trainer>