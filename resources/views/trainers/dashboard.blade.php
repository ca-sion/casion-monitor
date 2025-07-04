<x-layouts.trainer :title="$trainer->name">
    <flux:heading size="xl" level="1">Bonjour, {{ $trainer->first_name }}</flux:heading>

    <flux:text class="mb-6 mt-2 text-base">
        Voici ton tableau de bord. Les tendances sont calculées sur les
        <strong>{{ $period_label === 'all_time' ? 'toutes les données disponibles' : str_replace('_', ' ', $period_label) }}</strong>.
    </flux:text>

    <flux:separator variant="subtle" />

    <flux:table class="my-4 w-full">
        <flux:table.columns>
            <flux:table.column class="sticky left-0 bg-white dark:bg-zinc-900 z-10 w-48">Athlète</flux:table.column>
            <flux:table.column class="w-36">Dern. Connexion</flux:table.column>
            @foreach ($dashboard_metric_types as $metricType)
                <flux:table.column class="text-left w-48">{{ $metricType->getLabelShort() }}</flux:table.column>
                <flux:table.column class="text-center w-28">Moy. 7j</flux:table.column>
                <flux:table.column class="text-center w-28">Tendance</flux:table.column>
                <flux:table.column class="text-center w-48">Évolution</flux:table.column>
            @endforeach
            <flux:table.column class="text-center w-36">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($trainer->athletes as $athlete)
                @php
                    $athleteData = $athletes_overview_data[$athlete->id] ?? null;
                @endphp
                <flux:table.row>
                    <flux:table.cell class="sticky left-0 bg-white dark:bg-zinc-900 z-10">
                        {{ $athlete->name }}
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $athlete->last_connection?->locale('fr_CH')->isoFormat('L') ?? 'N/A' }}
                    </flux:table.cell>

                    @foreach ($dashboard_metric_types as $metricType)
                        @php
                            $chartData = $athleteData['chart_data'][$metricType->value] ?? null;
                            $trends = $athleteData['trends'][$metricType->value] ?? null;
                            $evolutionTrend = $athleteData['evolution_trends'][$metricType->value] ?? null;

                            $lastValue = null;
                            if ($chartData && !empty($chartData['data'])) {
                                // Récupérer la dernière valeur numérique non-null
                                $filteredData = array_values(array_filter($chartData['data'], fn($val) => $val !== null));
                                if (!empty($filteredData)) {
                                    $lastValue = end($filteredData);
                                }
                            }

                            $average7Days = $trends['averages']['Derniers 7 jours'] ?? null;
                        @endphp
                        <flux:table.cell>
                            @if ($lastValue !== null)
                                {{ number_format($lastValue, 2) }}{{ $metricType->getUnit() ? ' '.$metricType->getUnit() : '' }}
                            @else
                                <span class="text-zinc-500">N/A</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            @if ($average7Days !== null)
                                {{ number_format($average7Days, 2) }}{{ $metricType->getUnit() ? ' '.$metricType->getUnit() : '' }}
                            @else
                                <span class="text-zinc-500">N/A</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-center text-lg">
                            @if ($evolutionTrend['trend'] === 'not_applicable')
                                <span title="{{ $evolutionTrend['reason'] ?? 'Métrique non numérique' }}" class="text-zinc-400">―</span>
                            @elseif ($evolutionTrend['trend'] === 'not_enough_data')
                                <span title="Pas assez de données pour la tendance" class="text-zinc-400">...</span>
                            @else
                                @switch($evolutionTrend['trend'])
                                    @case('increasing')
                                        <span class="text-green-500" title="Tendance à la hausse">▲</span>
                                        @break
                                    @case('decreasing')
                                        <span class="text-red-500" title="Tendance à la baisse">▼</span>
                                        @break
                                    @case('stable')
                                        <span class="text-blue-500" title="Tendance stable">━</span>
                                        @break
                                    @default
                                        <span class="text-zinc-400" title="Tendance inconnue">?</span>
                                @endswitch
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            @if ($chartData && !empty($chartData['data']) && count(array_filter($chartData['data'], fn($val) => $val !== null)) >= 2)
                                <flux:chart class="aspect-[3/1] w-full" :value="collect($chartData['data'])->filter(fn($val) => $val !== null)->take(14)">
                                    <flux:chart.svg gutter="0">
                                        <flux:chart.line class="text-zinc-500 dark:text-zinc-400" />
                                    </flux:chart.svg>
                                </flux:chart>
                            @else
                                <span class="text-zinc-500 text-sm">Pas de données graphiques</span>
                            @endif
                        </flux:table.cell>
                    @endforeach
                    <flux:table.cell class="text-center">
                        <flux:link href="{{ $athlete->accountLink }}">Compte</flux:link>
                        <flux:link href="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete' => $athlete->id]) }}">Détail</flux:link>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

</x-layouts.trainer>