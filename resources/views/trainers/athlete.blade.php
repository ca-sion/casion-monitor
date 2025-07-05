<x-layouts.trainer :title="$athlete->name">
    <flux:heading size="xl" level="1">{{ $athlete->name }}</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">
        Voici les métriques détaillées de {{ $athlete->name }}.
    </flux:text>

    {{-- Section Profil Athlète --}}
    <flux:card class="mb-6">
        <flux:heading size="lg" level="2">Profil de l'athlète</flux:heading>
        <div class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2">
            <div>
                <flux:text class="font-semibold">Email:</flux:text>
                <flux:text>{{ $athlete->email }}</flux:text>
            </div>
            <div>
                <flux:text class="font-semibold">Date de naissance:</flux:text>
                <flux:text>{{ $athlete->birthdate ? \Carbon\Carbon::parse($athlete->birthdate)->locale('fr_CH')->isoFormat('L') : 'N/A' }}</flux:text>
            </div>
            <div>
                <flux:text class="font-semibold">Dernière connexion:</flux:text>
                <flux:text>{{ $athlete->last_connection ? $athlete->last_connection->timezone('Europe/Zurich')->locale('fr_CH')->diffForHumans() : 'Jamais' }}</flux:text>
            </div>
            {{-- Ajoutez d'autres informations personnelles si disponibles sur l'athlète --}}
        </div>
    </flux:card>

    <flux:separator variant="subtle" />

    {{-- Section Graphique des métriques --}}
    <flux:card class="my-6">
        <div class="flex items-center justify-between">
            <flux:heading size="lg" level="2">Historique des métriques</flux:heading>
            <form class="flex items-center space-x-2"
                id="chart-filter-form"
                action="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete' => $athlete->id]) }}"
                method="GET">
                <flux:select name="metric_type" onchange="document.getElementById('chart-filter-form').submit()">
                    @foreach ($available_metric_types_for_chart as $value => $label)
                        <option value="{{ $value }}" @selected($chart_metric_type->value === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </flux:select>
                <flux:select name="period" onchange="document.getElementById('chart-filter-form').submit()">
                    @foreach ($period_options as $value => $label)
                        <option value="{{ $value }}" @selected($period_label === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </flux:select>
            </form>
        </div>
        <div class="p-6">
            @if ($chart_data && !empty($chart_data['labels']) && count(array_filter($chart_data['data'], fn($val) => $val !== null)) >= 2)
                <flux:chart :value="$chart_data['labels_and_data']" class="h-64">
                    <flux:chart.svg>
                        <flux:chart.line field="value" />
                        <flux:chart.axis axis="x" field="label">
                            <flux:chart.axis.line />
                            <flux:chart.axis.tick />
                        </flux:chart.axis>
                        <flux:chart.axis axis="y">
                            <flux:chart.axis.grid />
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
                <flux:card class="flex h-32 items-center justify-center border-2 border-dashed p-6">
                    <flux:text class="text-center text-sm text-zinc-500">Pas assez de données (au moins 2 points de données non nuls) pour afficher le graphique pour la métrique et la période sélectionnées.</flux:text>
                </flux:card>
            @endif
        </div>
    </flux:card>

    <flux:separator variant="subtle" />

    {{-- Section Historique détaillé des métriques (tableau) --}}
    <flux:card class="my-6">
        <flux:heading size="lg" level="2">Historique détaillé des métriques</flux:heading>
        <div class="overflow-x-auto p-0"> {{-- p-0 for table, overflow-x-auto for horizontal scroll --}}
            <flux:table class="min-w-full"> {{-- min-w-full to ensure table takes full width in overflow context --}}
                <flux:table.columns>
                    <flux:table.column class="sticky left-0 z-10 w-48 bg-white dark:bg-zinc-900">Date</flux:table.column>
                    @foreach ($display_table_metric_types as $metricType)
                        <flux:table.column class="w-fit text-center">
                            {{ $metricType->getLabelShort() }}
                            <flux:tooltip content="{{ $metricType->getDescription() }}">
                                <flux:icon class="ms-2 inline size-4"
                                    size="sm"
                                    icon="information-circle"></flux:icon>
                            </flux:tooltip>
                        </flux:table.column>
                    @endforeach
                    <flux:table.column class="text-center">Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($daily_metrics_history as $rowData)
                        <flux:table.row>
                            <flux:table.cell class="sticky left-0 z-10 bg-white dark:bg-zinc-900">
                                {{ $rowData['date_formatted'] }}
                                <div class="text-xs text-zinc-500">({{ $rowData['day_of_week'] }})</div>
                            </flux:table.cell>
                            @foreach ($display_table_metric_types as $metricType)
                                <flux:table.cell class="text-center">
                                    @if (isset($rowData['metrics'][$metricType->value]) && $rowData['metrics'][$metricType->value] !== 'N/A')
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
                                Aucune entrée de métrique trouvée pour cet athlète sur la période sélectionnée.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

</x-layouts.trainer>
