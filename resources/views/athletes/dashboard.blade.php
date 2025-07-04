<x-layouts.athlete :title="$athlete->name">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <flux:heading size="xl" level="1" class="mb-4 sm:mb-0">Bonjour, {{ $athlete->first_name }}</flux:heading>

        {{-- Sélecteur de période pour l'athlète --}}
        <form action="{{ route('athletes.dashboard', ['hash' => $athlete->hash]) }}" method="GET" class="flex items-center space-x-2">
            <flux:text class="text-base whitespace-nowrap">Voir les données des:</flux:text>
            <flux:select name="period" onchange="this.form.submit()">
                @foreach ($period_options as $value => $label)
                    <option value="{{ $value }}" @selected($period_label === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </flux:select>
        </form>
    </div>

    <flux:text class="mb-6 mt-2 text-base">
        Voici ton tableau de bord personnalisé. Les statistiques sont calculées sur les
        <strong>{{ $period_options[$period_label] ?? 'données sélectionnées' }}</strong>.
    </flux:text>

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
            <flux:text class="mt-2">Tu peux ajouter de nouvelles métriques quotidiennes pour aujourd'hui ou un autre jour.</flux:text>
        </flux:card>
    </a>

    <flux:text class="mb-4 mt-8 text-lg font-semibold">Tes statistiques clés:</flux:text>

    {{-- Section des cartes de métriques individuelles --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 my-4">
        @foreach ($dashboard_metric_types as $metricType)
            @php
                $metricData = $metrics_data ?? null;

                $chartData = $metricData['chart_data'][$metricType->value] ?? null;
                $trends = $metricData['trends'][$metricType->value] ?? null;
                $evolutionTrend = $metricData['evolution_trends'][$metricType->value] ?? null;

                $lastValue = null;
                if (isset($chartData['data']) && !empty($chartData['data'])) {
                    $filteredData = array_values(array_filter($chartData['data'], fn($val) => $val !== null));
                    if (!empty($filteredData)) {
                        $lastValue = end($filteredData);
                    }
                }

                $average7Days = $trends['averages']['Derniers 7 jours'] ?? null;
                $average30Days = $trends['averages']['Derniers 30 jours'] ?? null;
                
                $trend7DaysValue = $average7Days;

                $evolutionTrendIcon = null;
                $evolutionTrendColor = 'zinc';

                if ($average7Days !== null && $average7Days !== 0 && $evolutionTrend) {
                    switch ($evolutionTrend['trend']) {
                        case 'increasing':
                            $evolutionTrendIcon = 'arrow-trending-up';
                            $evolutionTrendColor = 'lime';
                            break;
                        case 'decreasing':
                            $evolutionTrendIcon = 'arrow-trending-down';
                            $evolutionTrendColor = 'rose';
                            break;
                        case 'stable':
                            $evolutionTrendIcon = 'minus';
                            $evolutionTrendColor = 'zinc';
                            break;
                        default:
                        $evolutionTrendIcon = 'ellipsis-horizontal';
                        $evolutionTrendColor = 'zinc';
                            break;
                    }
                }
                $trendPercentage = $trend7DaysValue !== null ? number_format(abs($trend7DaysValue), 1).'%' : 'N/A';
            @endphp

            <flux:card class="p-4" size="sm">
                <flux:heading size="sm" class="mb-2 flex items-center justify-between">
                    {{ $metricType->getLabel() }}
                    @if ($metricType->getUnit())
                        <flux:badge size="xs" color="zinc">{{ $metricType->getUnit() }}</flux:badge>
                    @endif
                    @if ($metricType->getScale())
                        <flux:badge size="xs" color="zinc">sur {{ $metricType->getScale() }}</flux:badge>
                    @endif
                </flux:heading>
                <flux:text class="text-2xl font-bold">{{ $lastValue ?? 'N/A' }}</flux:text>
                <flux:text class="text-sm text-zinc-500 mb-2">Dernière valeur</flux:text>

                {{-- Graphique de la métrique --}}
                <flux:chart class="aspect-[3/1] w-full mb-2" :value="$chartData['data'] ?? []">
                    <flux:chart.svg gutter="0">
                        <flux:chart.line class="text-blue-500 dark:text-blue-400" />
                    </flux:chart.svg>
                </flux:chart>

                <div class="flex justify-between text-sm">
                    <div>
                        <flux:text class="text-zinc-500">Moy. 7j</flux:text>
                        <flux:text class="font-semibold">{{ number_format($average7Days, 1) ?? 'N/A' }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="text-zinc-500">Moy. 30j</flux:text>
                        <flux:text class="font-semibold">{{ number_format($average30Days, 1) ?? 'N/A' }}</flux:text>
                    </div>
                    <div class="text-right">
                        <flux:text class="text-zinc-500">Tendance 7j</flux:text>
                        <flux:text class="font-semibold flex items-center justify-end">
                            @if ($evolutionTrendIcon)
                                <flux:icon :name="$evolutionTrendIcon" :color="$evolutionTrendColor" class="mr-1" />
                            @endif
                            {{ $trendPercentage }}
                        </flux:text>
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:text class="mb-4 mt-8 text-lg font-semibold">Ton historique des métriques:</flux:text>
    <flux:table class="my-4">
        <flux:table.columns>
            <flux:table.column>Date</flux:table.column>
            <flux:table.column>Jour</flux:table.column>
            <flux:table.column class="text-center">Métriques enregistrées</flux:table.column>
            <flux:table.column>Aperçu des valeurs clés</flux:table.column>
            <flux:table.column class="text-center">Action</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($daily_metrics_history as $date => $metricDates)
                <flux:table.row>
                    <flux:table.cell>{{ \Carbon\Carbon::parse($date)->locale('fr_CH')->isoFormat('L') }}</flux:table.cell>
                    <flux:table.cell>{{ \Carbon\Carbon::parse($date)->locale('fr_CH')->isoFormat('dddd') }}</flux:table.cell>
                    <flux:table.cell class="text-center">
                        <flux:badge size="sm" color="info">{{ count($metricDates) }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="flex flex-wrap gap-2">
                        @php
                            // Filtrer les métriques spécifiques que nous voulons afficher en aperçu
                            $displayMetrics = [
                                \App\Enums\MetricType::MORNING_HRV->value => 'HRV',
                                \App\Enums\MetricType::POST_SESSION_SUBJECTIVE_FATIGUE->value => 'Fatigue Post-Sess.',
                                \App\Enums\MetricType::POST_SESSION_SESSION_LOAD->value => 'Charge Sess.',
                                \App\Enums\MetricType::MORNING_GENERAL_FATIGUE->value => 'Fatigue Gén.',
                            ];
                        @endphp
                        @foreach ($displayMetrics as $metricTypeValue => $metricLabel)
                            @php
                                $metric = $metricDates->firstWhere('metric_type', $metricTypeValue);
                            @endphp
                            @if ($metric && $metric->value !== null)
                                <flux:badge size="xs" color="zinc" class="flex items-center gap-1">
                                    <span class="font-medium">{{ $metricLabel }}:</span>
                                    {{ number_format($metric->value, 0) }}{{ $metric->unit ? ' '.$metric->unit : '' }}
                                </flux:badge>
                            @endif
                        @endforeach
                        {{-- Calculer les "autres" métriques restantes --}}
                        @if (count($metricDates->whereNotIn('metric_type.value', array_keys($displayMetrics))) > 0)
                            <flux:badge size="xs" color="zinc">+{{ count($metricDates->whereNotIn('metric_type.value', array_keys($displayMetrics))) }} autres</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-center">
                        @if ($metricDates->first())
                            <flux:link href="{{ $metricDates->first()->data->edit_link }}">Modifier</flux:link>
                        @else
                            N/A
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500 py-4">
                        Aucune entrée de métrique trouvée pour cette période.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

</x-layouts.athlete>