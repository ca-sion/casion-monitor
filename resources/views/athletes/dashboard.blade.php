<x-layouts.athlete :title="$athlete->name">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <flux:heading size="xl" level="1" class="mb-4 sm:mb-0">Bonjour {{ $athlete->first_name }}</flux:heading>

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

    {{-- Section pour ajouter une métrique (existante) --}}
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

    {{-- Section Volume et Intensité Planifiés de la semaine en cours --}}
    @if ($weekly_planned_volume || $weekly_planned_intensity)
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 my-4">
        <flux:card class="p-4 bg-blue-50! border-blue-400! hover:bg-zinc-50 dark:hover:bg-zinc-700" size="sm" color="blue">
            <flux:heading class="flex items-center gap-2">Volume planifié cette semaine</flux:heading>
            <flux:text class="mt-2 text-2xl font-bold text-blue-600">
                {{ number_format($weekly_planned_volume, 0) }} <span class="text-base font-normal text-blue-500">/5</span>
            </flux:text>
            <flux:text class="text-xs text-zinc-500">Volume d'entraînement prévu pour la semaine.</flux:text>
        </flux:card>

        <flux:card class="p-4 bg-purple-50! border-purple-400! hover:bg-zinc-50 dark:hover:bg-zinc-700" size="sm" color="purple">
            <flux:heading class="flex items-center gap-2">Intensité planifiée cette semaine</flux:heading>
            <flux:text class="mt-2 text-2xl font-bold text-purple-600">
                {{ number_format($weekly_planned_intensity, 0) }} <span class="text-base font-normal text-purple-500">/100</span>
            </flux:text>
            <flux:text class="text-xs text-zinc-500">Intensité d'entraînement prévue pour la semaine.</flux:text>
        </flux:card>
    </div>
    @endif

    {{-- Section Alertes --}}
    <flux:card class="my-6 p-6 bg-white dark:bg-zinc-800 shadow-lg rounded-lg">
        <flux:heading size="lg" level="2" class="mb-4 text-center">🔔 Tes Alertes Récentes</flux:heading>
        @if (!empty($alerts))
            <div class="flex flex-col gap-3">
                @foreach ($alerts as $alert)
                    <flux:badge size="md" inset="top bottom" class="w-full text-center py-2 px-4 whitespace-normal!"
                        color="{{ match($alert['type']) {
                            'danger' => 'rose',
                            'warning' => 'amber',
                            'info' => 'sky',
                            'success' => 'emerald',
                            default => 'zinc'
                        } }}">
                        <span>{{ $alert['message'] }}</span>
                    </flux:badge>
                @endforeach
            </div>
        @else
            <flux:text class="text-center text-zinc-500 italic">
                Aucune alerte détectée pour la période sélectionnée. Tout semble en ordre ! 🎉
            </flux:text>
        @endif
        @if ($athlete->gender === 'w' && $menstrualCycleInfo)
            @php
                $menstrualCycleBoxBorderColor = 'border-emerald-400';
                $menstrualCycleBoxBgColor = 'bg-emerald-50/50 dark:bg-emerald-950/50';

                if ($menstrualCycleInfo['phase'] === 'Aménorrhée' || $menstrualCycleInfo['phase'] === 'Oligoménorrhée') {
                    $menstrualCycleBoxBorderColor = 'border-rose-400';
                    $menstrualCycleBoxBgColor = 'bg-rose-50/50 dark:bg-rose-950/50';
                } elseif ($menstrualCycleInfo['phase'] === 'Potentiel retard ou cycle long') {
                    $menstrualCycleBoxBorderColor = 'border-amber-400';
                    $menstrualCycleBoxBgColor = 'bg-amber-50/50 dark:bg-amber-950/50';
                } elseif ($menstrualCycleInfo['phase'] === 'Inconnue') {
                    $menstrualCycleBoxBorderColor = 'border-sky-400';
                    $menstrualCycleBoxBgColor = 'bg-sky-50/50 dark:bg-sky-950/50';
                }
            @endphp
            <div class="mt-4 p-3 border rounded-md {{ $menstrualCycleBoxBorderColor }} {{ $menstrualCycleBoxBgColor }}">
                <flux:text class="text-sm font-semibold">Cycle Menstruel:</flux:text>
                <flux:text class="text-xs">
                    Phase: <span class="font-medium">{{ $menstrualCycleInfo['phase'] }}</span><br>
                    Jours dans la phase: <span class="font-medium">{{ intval($menstrualCycleInfo['days_in_phase']) ?? 'N/A' }}</span><br>
                    Longueur moy. cycle: <span class="font-medium">{{ $menstrualCycleInfo['cycle_length_avg'] ?? 'N/A' }} jours</span>
                    @if($menstrualCycleInfo['last_period_start'])
                        <br>Dernières règles: <span class="font-medium">{{ \Carbon\Carbon::parse($menstrualCycleInfo['last_period_start'])->locale('fr_CH')->isoFormat('L') }}</span>
                    @endif
                </flux:text>
            </div>
        @endif
    </flux:card>


    <flux:text class="mb-4 mt-8 text-lg font-semibold">📈 Tes statistiques clés:</flux:text>

    {{-- Section des cartes de métriques individuelles (existante) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 my-4">
        @foreach ($dashboard_metrics_data as $metricTypeKey => $metricData)
            <flux:card class="p-4 transform transition-transform duration-200 hover:scale-105 hover:shadow-xl" size="sm">
                <div class="flex items-center justify-between mb-2">
                        <div>
                            <flux:text class="text-xs font-semibold uppercase text-zinc-500 inline">{{ $metricData['short_label'] }}</flux:text>
                            <x-filament::icon-button
                                class="inline ms-1"
                                style="vertical-align: text-bottom;"
                                icon="heroicon-o-information-circle"
                                tooltip="{{ $metricData['description'] }}"
                                label="{{ $metricData['description'] }}"
                                color="gray"
                                size="sm"
                                x-data="{}"
                            />
                        </div>
                    @if ($metricData['is_numerical'] && $metricData['trend_icon'] && $metricData['trend_percentage'] !== 'N/A')
                        <flux:badge size="xs" color="{{ $metricData['trend_color'] }}">
                            <div class="flex items-center gap-1">
                                <flux:icon name="{{ $metricData['trend_icon'] }}" variant="mini" class="-mr-0.5" />
                                <span>{{ $metricData['trend_percentage'] }}</span>
                            </div>
                        </flux:badge>
                    @endif
                </div>
                <flux:heading size="lg" level="2" class="mb-4 text-slate-600 dark:text-slate-400">
                    {{ $metricData['formatted_last_value'] }}
                    <x-filament::icon-button
                        class="inline ms-1"
                        icon="heroicon-o-information-circle"
                        tooltip="Dernière valeur enregistrée pour cette métrique."
                        label="Dernière valeur enregistrée pour cette métrique."
                        color="gray"
                        size="xs"
                        x-data="{}"
                    />
                </flux:heading>
                <div class="mb-4">
                    {{-- Utilisation du composant Flux UI pour le graphique - MISE À JOUR AVEC VOTRE SOLUTION --}}
                    @if (!empty($metricData['chart_data']['labels_and_data']) && count(array_filter($metricData['chart_data']['data'], fn($val) => $val !== null)) >= 2)
                        <flux:chart :value="$metricData['chart_data']['labels_and_data']" class="h-24">
                            <flux:chart.svg>
                                <flux:chart.line field="value" class="stroke-slate-500!" />
                                <flux:chart.axis axis="x" field="label" class="text-xs text-zinc-400">
                                    <flux:chart.axis.line />
                                    <flux:chart.axis.tick />
                                </flux:chart.axis>
                                <flux:chart.axis axis="y" class="text-xs text-zinc-400">
                                    <flux:chart.axis.grid stroke-dasharray="2 2" />
                                    <flux:chart.axis.tick />
                                </flux:chart.axis>
                                <flux:chart.cursor />
                            </flux:chart.svg>
                            <flux:chart.tooltip>
                                <flux:chart.tooltip.heading field="label" :format="['year' => 'numeric', 'month' => 'numeric', 'day' => 'numeric']" />
                                <flux:chart.tooltip.value field="value" label="Valeur" />
                            </flux:chart.tooltip>
                        </flux:chart>
                    @else
                        <flux:card class="flex h-24 items-center justify-center border-2 border-dashed p-4 text-zinc-400">
                            <flux:text class="text-center text-sm">Pas assez de données pour le graphique.</flux:text>
                        </flux:card>
                    @endif
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:separator variant="subtle" class="my-8" />

    {{-- Section Tableau de toutes les données métriques brutes --}}
    <flux:card class="my-6 p-6 bg-white dark:bg-zinc-800 shadow-lg rounded-lg">
        <flux:heading size="lg" level="2" class="mb-4 text-center">📋 Tes Données Quotidiennes Détaillées</flux:heading>
        <flux:text class="mb-4 text-zinc-600 dark:text-zinc-400 text-center">
            Explore tes entrées de métriques jour par jour. Clique sur "Modifier" pour ajuster une entrée.
        </flux:text>

        <div class="overflow-x-auto">
            <flux:table class="min-w-full text-nowrap">
                <flux:table.columns>
                    <flux:table.column class="z-1 sticky left-0 bg-white dark:bg-zinc-900 w-32">Date</flux:table.column>
                    @foreach ($display_table_metric_types as $metricType)
                        <flux:table.column class="text-center">
                            {{ $metricType->getLabelShort() }}
                            <x-filament::icon-button
                                class="inline ms-1"
                                icon="heroicon-o-information-circle"
                                tooltip="{{ $metricType->getDescription() }}"
                                label="{{ $metricType->getDescription() }}"
                                color="gray"
                                size="sm"
                                x-data="{}"
                            />
                        </flux:table.column>
                    @endforeach
                    <flux:table.column class="text-center w-24">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    {{-- Utilisation de $processed_daily_metrics_for_table --}}
                    @forelse ($daily_metrics_grouped_by_date as $date => $rowData)
                        <flux:table.row>
                            <flux:table.cell class="z-1 sticky left-0 bg-white dark:bg-zinc-900 font-semibold">
                                {{ $rowData['date'] }}
                            </flux:table.cell>
                            @foreach ($display_table_metric_types as $metricType)
                                <flux:table.cell class="text-center">
                                    @if (isset($rowData['metrics'][$metricType->value]))
                                        <flux:badge size="xs" color="zinc">
                                            {{ $rowData['metrics'][$metricType->value] }}
                                        </flux:badge>
                                    @else
                                        <flux:text class="text-zinc-500">N/A</flux:text>
                                    @endif
                                </flux:table.cell>
                            @endforeach
                            <flux:table.cell class="text-center">
                                @if ($rowData['edit_link'])
                                    <flux:link href="{{ $rowData['edit_link'] }}">Modifier</flux:link>
                                @else
                                    <flux:text class="text-zinc-400">N/A</flux:text>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell class="py-4 text-center text-zinc-500" colspan="{{ count($display_table_metric_types) + 2 }}">
                                Aucune entrée de métrique trouvée pour cette période. Commence à enregistrer tes données pour les voir ici ! ✨
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

</x-layouts.athlete>