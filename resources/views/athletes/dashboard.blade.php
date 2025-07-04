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
        @foreach ($dashboard_metrics_data as $metricTypeKey => $metricData)
            <flux:card class="p-4" size="sm">
                <div class="flex items-center justify-between mb-2">
                    <flux:tooltip content="{{ $metricData['description'] }}" icon="information-circle">
                        <flux:text class="text-xs font-semibold uppercase text-zinc-500">{{ $metricData['short_label'] }}</flux:text>
                    </flux:tooltip>
                    @if ($metricData['is_numerical'] && $metricData['trend_icon'] && $metricData['trend_percentage'] !== 'N/A')
                        <flux:tooltip content="La tendance compare la moyenne des 7 derniers jours à la moyenne des 30 derniers jours." icon="information-circle">
                            <flux:badge size="xs" color="{{ $metricData['trend_color'] }}">
                                <div class="flex items-center gap-1">
                                    <flux:icon name="{{ $metricData['trend_icon'] }}" variant="mini" class="-mr-0.5" />
                                    <span>{{ $metricData['trend_percentage'] }}</span>
                                </div>
                            </flux:badge>
                        </flux:tooltip>
                    @endif
                </div>
                <flux:tooltip content="Ceci est la dernière valeur enregistrée pour cette métrique sur la période sélectionnée." icon="information-circle">
                    <flux:heading size="lg" level="2" class="mb-4">
                        {{ $metricData['formatted_last_value'] }}
                    </flux:heading>
                </flux:tooltip>

                <div class="mb-4">
                    {{-- Utilisation du composant Flux UI pour le graphique - MISE À JOUR AVEC VOTRE SOLUTION --}}
                    @if (!empty($metricData['chart_data']['labels']) && !empty($metricData['chart_data']['data']))
                        <flux:chart class="aspect-[3/1] w-full mb-2" :value="$metricData['chart_data']['data']">
                            <flux:chart.svg gutter="0">
                                <flux:chart.line class="text-blue-500 dark:text-blue-400" />
                            </flux:chart.svg>
                        </flux:chart>
                    @else
                        <flux:text class="text-center text-zinc-500">Pas assez de données pour afficher le graphique.</flux:text>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-2 text-sm text-zinc-600">
                    <div>
                        Moy. 7j:
                        <flux:tooltip content="Moyenne des valeurs sur les 7 derniers jours." icon="information-circle">
                            <span class="font-medium">{{ $metricData['formatted_average_7_days'] }}</span>
                        </flux:tooltip>
                    </div>
                    <div>
                        Moy. 30j:
                        <flux:tooltip content="Moyenne des valeurs sur les 30 derniers jours." icon="information-circle">
                            <span class="font-medium">{{ $metricData['formatted_average_30_days'] }}</span>
                        </flux:tooltip>
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:separator variant="subtle" class="my-8" />

    <flux:text class="mb-4 mt-8 text-lg font-semibold">Ton historique des métriques quotidiennes:</flux:text>

    <flux:table class="my-4">
        <flux:table.columns>
            <flux:table.column>Date</flux:table.column>
            @foreach ($dashboard_metric_types as $metricType)
                <flux:table.column>
                    <flux:tooltip content="{{ $metricType->getDescription() }}" icon="information-circle">
                        {{ $metricType->getLabelShort() }}
                    </flux:tooltip>
                </flux:table.column>
            @endforeach
            <flux:table.column class="text-center">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($daily_metrics_history as $date => $metricDates)
                <flux:table.row>
                    <flux:table.cell>
                        {{ \Carbon\Carbon::parse($date)->locale('fr_CH')->isoFormat('L') }}
                        ({{ \Carbon\Carbon::parse($date)->locale('fr_CH')->isoFormat('dddd') }})
                    </flux:table.cell>
                    @foreach ($dashboard_metric_types as $metricType)
                        <flux:table.cell>
                            @php
                                $displayMetric = $metricDates->where('metric_type', $metricType->value)->first();
                            @endphp
                            @if ($displayMetric)
                                <flux:badge size="xs" color="zinc">
                                    <span class="font-medium">{{ $displayMetric->metric_type->getLabelShort() }}:</span>
                                    {{ $displayMetric->data->formatted_value }}
                                </flux:badge>
                            @else
                                N/A
                            @endif
                        </flux:table.cell>
                    @endforeach
                    <flux:table.cell class="text-center">
                        @if ($metricDates->first())
                            <flux:link href="{{ $metricDates->first()->data->edit_link }}">Modifier</flux:link>
                        @else
                            N/A
                        @endif
                    </flux:table.table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="{{ count($dashboard_metric_types) + 2 }}" class="text-center text-zinc-500 py-4">
                        Aucune entrée de métrique trouvée pour cette période.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

</x-layouts.athlete>