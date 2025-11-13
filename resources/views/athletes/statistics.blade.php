<x-layouts.athlete :title="$athlete->name">
    <div class="container mx-auto">
        {{-- En-tête --}}
        <div class="py-6">
            <flux:heading class="mb-1 font-extrabold text-gray-900"
                size="xl"
                level="1">
                Statistiques
            </flux:heading>
            <flux:text class="text-md text-gray-600">
                Tes statistiques et données.
            </flux:text>
        </div>

        {{-- Nouveau graphique hebdomadaire --}}
        @if (!empty($combinedWeeklyChartData) && count(array_filter(array_column($combinedWeeklyChartData, 'sbm'))) >= 2)
            <flux:card class="my-4 p-4 dark:border dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading class="mb-4"
                    size="md"
                    level="3">Suivi SBM</flux:heading>
                <flux:chart class="h-48" :value="$combinedWeeklyChartData">
                    <flux:chart.svg>
                        <flux:chart.line class="stroke-blue-500!" field="sbm" />
                        <flux:chart.axis class="text-xs text-zinc-400"
                            axis="x"
                            field="label">
                            <flux:chart.axis.line />
                            <flux:chart.axis.tick />
                        </flux:chart.axis>
                        <flux:chart.axis class="text-xs text-zinc-400"
                            axis="y"
                            field="sbm">
                            <flux:chart.axis.grid stroke-dasharray="2 2" />
                            <flux:chart.axis.tick />
                        </flux:chart.axis>
                        <flux:chart.cursor />
                    </flux:chart.svg>
                    <flux:chart.tooltip>
                        <flux:chart.tooltip.heading field="label" />
                        <flux:chart.tooltip.value field="sbm"
                            label="SBM"
                            color="blue" />
                    </flux:chart.tooltip>
                </flux:chart>
            </flux:card>
            <flux:card class="my-4 p-4 dark:border dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading class="mb-4"
                    size="md"
                    level="3">Suivi CIH/CPH</flux:heading>
                <flux:chart class="h-48" :value="$combinedWeeklyChartData">
                    <flux:chart.svg>
                        <flux:chart.line class="stroke-emerald-500!" field="ratio" />
                        <flux:chart.axis class="text-xs text-zinc-400"
                            axis="x"
                            field="label">
                            <flux:chart.axis.line />
                            <flux:chart.axis.tick />
                        </flux:chart.axis>
                        <flux:chart.axis class="text-xs text-zinc-400"
                            axis="y"
                            position="right"
                            field="ratio">
                            <flux:chart.axis.grid stroke-dasharray="2 2" />
                            <flux:chart.axis.tick />
                        </flux:chart.axis>
                        <flux:chart.cursor />
                    </flux:chart.svg>
                    <flux:chart.tooltip>
                        <flux:chart.tooltip.heading field="label" />
                        <flux:chart.tooltip.value field="ratio"
                            label="Ratio"
                            color="emerald" />
                    </flux:chart.tooltip>
                </flux:chart>
            </flux:card>
        @endif

        {{-- Sélecteur de période pour l'athlète --}}
        <form class="flex items-center space-x-2"
            action="{{ route('athletes.dashboard', ['hash' => $athlete->hash]) }}"
            method="GET">
            <flux:text class="whitespace-nowrap text-base">Voir les données des:</flux:text>
            <flux:select name="period" onchange="this.form.submit()">
                @foreach ($period_options as $value => $label)
                    <option value="{{ $value }}" @selected($period_label === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </flux:select>
        </form>

        {{-- Section des cartes de métriques individuelles (existante) --}}
        <div class="my-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($dashboard_metrics_data as $metricTypeKey => $metricData)
                <flux:card class="transform p-4 transition-transform duration-200 hover:scale-105 hover:shadow-xl dark:border dark:border-zinc-700 dark:bg-zinc-800" size="sm">
                    <div class="mb-2 flex items-center justify-between">
                        <div>
                            <flux:text class="inline text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ $metricData['short_label'] }}</flux:text>
                            <x-filament::icon-button class="ms-1 inline"
                                style="vertical-align: text-bottom;"
                                icon="heroicon-o-information-circle"
                                tooltip="{{ $metricData['description'] }}"
                                label="{{ $metricData['description'] }}"
                                color="gray"
                                size="sm"
                                x-data="{}" />
                        </div>
                        @if ($metricData['is_numerical'] && $metricData['trend_icon'] && $metricData['trend_percentage'] !== 'n/a')
                            <flux:badge size="xs" color="{{ $metricData['trend_color'] }}">
                                <div class="flex items-center gap-1">
                                    <flux:icon class="-mr-0.5"
                                        name="{{ $metricData['trend_icon'] }}"
                                        variant="mini" />
                                    <span>{{ $metricData['trend_percentage'] }}</span>
                                </div>
                            </flux:badge>
                        @endif
                    </div>
                    <flux:heading class="mb-4 text-slate-600 dark:text-slate-400"
                        size="lg"
                        level="2">
                        {{ $metricData['formatted_last_value'] }}
                        <x-filament::icon-button class="ms-1 inline"
                            icon="heroicon-o-information-circle"
                            tooltip="Dernière valeur enregistrée pour cette métrique."
                            label="Dernière valeur enregistrée pour cette métrique."
                            color="gray"
                            size="xs"
                            x-data="{}" />
                    </flux:heading>
                    <div class="mb-4">
                        {{-- Utilisation du composant Flux UI pour le graphique - MISE À JOUR AVEC VOTRE SOLUTION --}}
                        @if (!empty($metricData['chart_data']['labels_and_data']) && count(array_filter($metricData['chart_data']['data'], fn($val) => $val !== null)) >= 2)
                            <flux:chart class="h-24" :value="$metricData['chart_data']['labels_and_data']">
                                <flux:chart.svg>
                                    <flux:chart.line class="stroke-slate-500!" field="value" />
                                    <flux:chart.axis class="text-xs text-zinc-400"
                                        axis="x"
                                        field="label">
                                        <flux:chart.axis.line />
                                        <flux:chart.axis.tick />
                                    </flux:chart.axis>
                                    <flux:chart.axis class="text-xs text-zinc-400" axis="y">
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
                            <flux:card class="flex h-24 items-center justify-center border-2 border-dashed p-4 text-zinc-400 dark:border-zinc-700">
                                <flux:text class="text-center text-sm dark:text-zinc-500">Pas assez de données pour le graphique.</flux:text>
                            </flux:card>
                        @endif
                    </div>
                </flux:card>
            @endforeach
        </div>

        <flux:separator class="my-8" variant="subtle" />

        {{-- Section Tableau de toutes les données métriques brutes --}}
        <flux:card class="my-6 rounded-lg p-6 shadow-lg dark:border dark:border-zinc-700 dark:bg-zinc-800">
            <flux:heading class="mb-4 text-center dark:text-zinc-200"
                size="lg"
                level="2">Données quotidiennes</flux:heading>
            <flux:text class="mb-4 text-center text-zinc-600 dark:text-zinc-400">
                Explore tes entrées de métriques jour par jour. Clique sur "Modifier" pour ajuster une entrée.
            </flux:text>

            <div class="overflow-x-auto">
                <flux:table class="min-w-full text-nowrap">
                    <flux:table.columns>
                        <flux:table.column class="z-1 sticky left-0 w-32">Date</flux:table.column>
                        @foreach ($display_table_metric_types as $metricType)
                            <flux:table.column class="text-center">
                                {{ $metricType->getLabelShort() }}
                                <x-filament::icon-button class="ms-1 inline"
                                    icon="heroicon-o-information-circle"
                                    tooltip="{!! $metricType->getDescription() !!}"
                                    label="{{ $metricType->getDescription() }}"
                                    color="gray"
                                    size="sm"
                                    x-data="{}" />
                            </flux:table.column>
                        @endforeach
                        <flux:table.column class="w-24 text-center">Actions</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        {{-- Utilisation de $processed_daily_metrics_for_table --}}
                        @forelse ($daily_metrics_grouped_by_date as $date => $rowData)
                            <flux:table.row>
                                <flux:table.cell class="z-1 sticky left-0 font-semibold">
                                    {{ $rowData['date'] }}
                                </flux:table.cell>
                                @foreach ($display_table_metric_types as $metricType)
                                    <flux:table.cell class="text-center">
                                        @if (isset($rowData['metrics'][$metricType->value]))
                                            <flux:badge size="sm" color="{{ $metricType->getColor() }}">
                                                <span class="{{ $metricType->getIconifyTailwind() }} me-1 size-4"></span>
                                                {{ $rowData['metrics'][$metricType->value] }}
                                            </flux:badge>
                                        @else
                                            <flux:text class="text-zinc-500 dark:text-zinc-400">-</flux:text>
                                        @endif
                                    </flux:table.cell>
                                @endforeach
                                <flux:table.cell class="text-center">
                                    @if ($rowData['edit_link'])
                                        <flux:link href="{{ $rowData['edit_link'] }}">Modifier</flux:link>
                                    @else
                                        <flux:text class="text-zinc-400 dark:text-zinc-500">n/a</flux:text>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell class="py-4 text-center text-zinc-500 dark:text-zinc-400" colspan="{{ count($display_table_metric_types) + 2 }}">
                                    Aucune entrée de métrique trouvée pour cette période. Commence à enregistrer tes données pour les voir ici ! ✨
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
</x-layouts.athlete>
