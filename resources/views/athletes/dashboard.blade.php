<x-layouts.athlete :title="$athlete->name">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <flux:heading class="mb-4 sm:mb-0"
            size="xl"
            level="1">{{ $athlete->first_name }}</flux:heading>

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
    </div>

    <flux:separator variant="subtle" />

    {{-- Section pour ajouter une métrique (existante) --}}
    <a href="{{ route('athletes.metrics.daily.form', ['hash' => $athlete->hash]) }}" aria-label="Ajouter une métrique">
        <flux:card class="bg-lime-50! border-lime-400! my-4 hover:bg-zinc-50 dark:hover:bg-zinc-700"
            size="sm"
            color="lime">
            <flux:heading class="flex items-center gap-2">Métriques quotidienne
                <flux:icon class="ml-auto text-lime-600"
                    name="plus"
                    variant="micro" />
            </flux:heading>
        </flux:card>
    </a>

    <flux:separator class="mb-4" variant="subtle" />
    <flux:heading class="text-base">Aujourd'hui</flux:heading>
    @php
        $todayDailyMetrics = $daily_metrics_grouped_by_date->get(now()->toDateString());
    @endphp
    @if ($todayDailyMetrics)
        <div class="mb-4 mt-2 flex flex-wrap gap-1">
            @foreach ($todayDailyMetrics['metrics'] as $metricType => $metricValue)
                @php
                    $metricTypeEnum = \App\Enums\MetricType::from($metricType);
                @endphp
                <flux:tooltip content="{{ $metricTypeEnum->getLabel() }}" x-data="{}">
                    <flux:badge size="sm" color="{{ $metricTypeEnum->getColor() }}">
                        <span class="{{ $metricTypeEnum->getIconifyTailwind() }} me-1 size-4"></span>
                        {{ $metricValue }}
                    </flux:badge>
                </flux:tooltip>
            @endforeach
        </div>
    @else
        <flux:text class="mb-4 mt-2 text-xs text-zinc-500">
            Pas encore de données.
            <a class="underline"
                href="{{ route('athletes.metrics.daily.form', ['hash' => $athlete->hash]) }}"
                aria-label="Ajouter une métrique">Ajouter</a>.
        </flux:text>
    @endif
    @if ($today_feedbacks)
        <div class="mb-4 mt-2 flex flex-col gap-1">
            @foreach ($today_feedbacks as $feedback)
                <flux:callout class="p-0!"
                    :icon="$feedback->author_type === 'trainer' ? 'user-circle' : 'document-text'"
                    :color="$feedback->author_type === 'trainer' ? 'purple' : 'stone'">
                    <flux:callout.text class="text-xs">{!! nl2br(e($feedback->content)) !!}</flux:callout.text>
                </flux:callout>
            @endforeach
        </div>
    @endif

    {{-- Section Volume et Intensité Planifiés de la semaine en cours --}}
    @if ($weekly_planned_volume || $weekly_planned_intensity)
        <flux:separator class="mb-4" variant="subtle" />
        <flux:heading class="text-base">Cette semaine</flux:heading>
        <div class="mb-4 mt-2 grid grid-cols-2 gap-4 sm:grid-cols-2">
            <flux:card class="bg-blue-50! border-blue-400! p-4 hover:bg-zinc-50 dark:hover:bg-zinc-700"
                size="sm"
                color="blue">
                <flux:heading class="flex items-center gap-2">Volume</flux:heading>
                <flux:text class="mt-2 text-2xl font-bold text-blue-600">
                    {{ number_format($weekly_planned_volume, 0) }} <span class="text-base font-normal text-blue-500">/5</span>
                </flux:text>
            </flux:card>

            <flux:card class="bg-purple-50! border-purple-400! p-4 hover:bg-zinc-50 dark:hover:bg-zinc-700"
                size="sm"
                color="purple">
                <flux:heading class="flex items-center gap-2">Intensité</flux:heading>
                <flux:text class="mt-2 text-2xl font-bold text-purple-600">
                    {{ number_format($weekly_planned_intensity, 0) }} <span class="text-base font-normal text-purple-500">/100</span>
                </flux:text>
            </flux:card>
        </div>
    @endif

    {{-- Section Alertes --}}
    <flux:card class="my-6 rounded-lg bg-white p-6 shadow-lg dark:bg-zinc-800">
        <flux:heading class="mb-4 text-center text-base">🔔 Alertes</flux:heading>
        @if (!empty($alerts))
            <div class="flex flex-col gap-3">
                @foreach ($alerts as $alert)
                    <flux:badge class="whitespace-normal! w-full px-4 py-2 text-center"
                        size="md"
                        inset="top bottom"
                        color="{{ match ($alert['type']) {
                            'danger' => 'rose',
                            'warning' => 'amber',
                            'info' => 'sky',
                            'success' => 'emerald',
                            default => 'zinc',
                        } }}">
                        <span>{{ $alert['message'] }}</span>
                    </flux:badge>
                @endforeach
            </div>
        @else
            <flux:text class="text-center italic text-zinc-500">
                Aucune alerte détectée pour la période sélectionnée. Tout semble en ordre ! 🎉
            </flux:text>
        @endif
        @if ($athlete->gender->value === 'w' && $menstrualCycleInfo)
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
            <div class="{{ $menstrualCycleBoxBorderColor }} {{ $menstrualCycleBoxBgColor }} mt-4 rounded-md border p-3">
                <flux:text class="text-sm font-semibold">Cycle Menstruel:</flux:text>
                <flux:text class="text-xs">
                    Phase: <span class="font-medium">{{ $menstrualCycleInfo['phase'] }}</span><br>
                    Jours dans la phase: <span class="font-medium">{{ intval($menstrualCycleInfo['days_in_phase']) ?? 'n/a' }}</span><br>
                    Longueur moy. cycle: <span class="font-medium">{{ $menstrualCycleInfo['cycle_length_avg'] ?? 'n/a' }} jours</span>
                    @if ($menstrualCycleInfo['last_period_start'])
                        <br>Dernières règles: <span class="font-medium">{{ \Carbon\Carbon::parse($menstrualCycleInfo['last_period_start'])->locale('fr_CH')->isoFormat('L') }}</span>
                    @endif
                </flux:text>
            </div>
        @endif
    </flux:card>

    {{-- Section Protocoles de Récupération --}}
    <flux:card class="my-6 rounded-lg bg-white p-6 shadow-lg dark:bg-zinc-800">
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="lg" level="2">🧘 Protocoles</flux:heading>
            <flux:button href="{{ route('athletes.recovery-protocols.create', ['hash' => $athlete->hash]) }}"
                variant="filled"
                icon="plus">Ajouter un protocole</flux:button>
        </div>
        @if ($recoveryProtocols->isEmpty())
            <div class="p-8">
                <flux:callout icon="chat-bubble-left-right">
                    <flux:callout.heading>Aucun protocole de récupération enregistré.</flux:callout.heading>

                    <flux:callout.text>
                        Enregistre tes séances de récupération pour les suivre ici.
                    </flux:callout.text>
                </flux:callout>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($recoveryProtocols as $protocol)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="mb-3 flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ $protocol->recovery_type->getLabel() }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ $protocol->date->format('d.m.Y') }}
                                    @if ($protocol->duration_minutes)
                                        • {{ $protocol->duration_minutes }} minutes
                                    @endif
                                    @if ($protocol->relatedInjury)
                                        • Lié à la blessure: {{ $protocol->relatedInjury->type }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center space-x-2">
                                @if ($protocol->effect_on_pain_intensity)
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                                        Douleur: {{ $protocol->effect_on_pain_intensity }}/10
                                    </span>
                                @endif
                                @if ($protocol->effectiveness_rating)
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                                        Efficacité: {{ $protocol->effectiveness_rating }}/5
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if ($protocol->notes)
                            <div class="mb-3">
                                <p class="mb-1 text-xs font-medium text-gray-700">Notes:</p>
                                <p class="rounded bg-gray-50 p-2 text-sm text-gray-800">{{ $protocol->notes }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>

    <flux:separator class="my-8" variant="subtle" />

    <flux:text class="mb-4 mt-8 text-lg font-semibold">📈 Statistiques</flux:text>

    {{-- Section des cartes de métriques individuelles (existante) --}}
    <div class="my-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($dashboard_metrics_data as $metricTypeKey => $metricData)
            <flux:card class="transform p-4 transition-transform duration-200 hover:scale-105 hover:shadow-xl" size="sm">
                <div class="mb-2 flex items-center justify-between">
                    <div>
                        <flux:text class="inline text-xs font-semibold uppercase text-zinc-500">{{ $metricData['short_label'] }}</flux:text>
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
                        <flux:card class="flex h-24 items-center justify-center border-2 border-dashed p-4 text-zinc-400">
                            <flux:text class="text-center text-sm">Pas assez de données pour le graphique.</flux:text>
                        </flux:card>
                    @endif
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:separator class="my-8" variant="subtle" />

    {{-- Section Tableau de toutes les données métriques brutes --}}
    <flux:card class="my-6 rounded-lg bg-white p-6 shadow-lg dark:bg-zinc-800">
        <flux:heading class="mb-4 text-center"
            size="lg"
            level="2">📋 Données quotidiennes</flux:heading>
        <flux:text class="mb-4 text-center text-zinc-600 dark:text-zinc-400">
            Explore tes entrées de métriques jour par jour. Clique sur "Modifier" pour ajuster une entrée.
        </flux:text>

        <div class="overflow-x-auto">
            <flux:table class="min-w-full text-nowrap">
                <flux:table.columns>
                    <flux:table.column class="z-1 sticky left-0 w-32 bg-white dark:bg-zinc-900">Date</flux:table.column>
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
                            <flux:table.cell class="z-1 sticky left-0 bg-white font-semibold dark:bg-zinc-900">
                                {{ $rowData['date'] }}
                            </flux:table.cell>
                            @foreach ($display_table_metric_types as $metricType)
                                <flux:table.cell class="text-center">
                                    @if (isset($rowData['metrics'][$metricType->value]))
                                        <div>
                                            <flux:badge size="sm" color="{{ $metricType->getColor() }}">
                                                <span class="{{ $metricType->getIconifyTailwind() }} me-1 size-4"></span>
                                                {{ $rowData['metrics'][$metricType->value] }}
                                            </flux:badge>
                                        </div>
                                    @else
                                        <flux:text class="text-zinc-500">-</flux:text>
                                    @endif
                                </flux:table.cell>
                            @endforeach
                            <flux:table.cell class="text-center">
                                @if ($rowData['edit_link'])
                                    <flux:link href="{{ $rowData['edit_link'] }}">Modifier</flux:link>
                                @else
                                    <flux:text class="text-zinc-400">n/a</flux:text>
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
