<x-layouts.trainer :title="'Détail de l\'athlète '.$athlete->name">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <flux:heading size="xl" level="1" class="mb-4 sm:mb-0">Détail de l'athlète {{ $athlete->name }}</flux:heading>

        {{-- Sélecteur de période pour l'entraîneur --}}
        <form action="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete' => $athlete->id]) }}" method="GET" class="flex items-center space-x-2">
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
        Voici le tableau de bord détaillé de {{ $athlete->name }}. Les statistiques sont calculées sur les
        <strong>{{ $period_options[$period_label] ?? 'données sélectionnées' }}</strong>.
    </flux:text>

    {{-- Section Profil Athlète (compact, refonte punchy) --}}
    <flux:card class="mb-6 p-4 bg-gradient-to-br from-slate-600 to-slate-700 text-white shadow-lg rounded-lg overflow-hidden relative">
        {{-- Effet de fond subtil --}}
        <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cucG9ydC5vcmcvMjAwMC9zdmciPjxnIGZpbGw9Im5vbmUiIGZpbGwtcnVsZT0iZXZlbm9kZCI+PHBhdGggZmlsbD0iI0ZGRkZGRiIgZD0iTTAgMGg2MHY2MEgweiIvPjxwYXRoIGZpbGw9IiMwMDAwMDAiIGQ9Ik0wIDBoMzB2MzBIMHoiIG9wYWNpdHk9Ii4wNSIvPjxwYXRoIGZpbGw9IiMwMDAwMDAiIGQ9Ik0zMCAzMGg2MHY2MEgzMHoiIG9wYWNpdHk9Ii4xIi8+PC9nPg==');"></div>

        <div class="relative z-10">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4">
                <flux:heading size="lg" level="2" class="text-white mb-2 sm:mb-0">
                    {{ $athlete->name }}
                </flux:heading>
                <div class="flex items-center space-x-2 text-sm">
                    @if ($athlete->last_connection)
                        <flux:icon name="clock" class="size-4 text-white/80" />
                        <flux:text class="text-white/80">
                            Connecté {{ $athlete->last_connection->timezone('Europe/Zurich')->locale('fr_CH')->diffForHumans() }}
                        </flux:text>
                    @else
                        <flux:icon name="clock" class="size-4 text-white/80" />
                        <flux:text class="text-white/80">Jamais connecté</flux:text>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-3 text-sm">

                @if ($athlete->birthdate)
                    <div class="flex items-center space-x-2">
                        <flux:icon name="cake" class="size-5 text-slate-200" />
                        <flux:text class="text-white">
                            {{ \Carbon\Carbon::parse($athlete->birthdate)->locale('fr_CH')->isoFormat('L') }}
                        </flux:text>
                    </div>
                @endif

                @if ($athlete->gender->value)
                    <div class="flex items-center space-x-2">
                        @if ($athlete->gender->value === 'm')
                            <flux:icon name="user" class="size-5 text-slate-200" />
                            <flux:text class="text-white">Homme</flux:text>
                        @else
                            <flux:icon name="user" class="size-5 text-slate-200" />
                            <flux:text class="text-white">Femme</flux:text>
                        @endif
                    </div>
                @endif

                @if ($athlete->email)
                    <div class="flex items-center space-x-2">
                        <flux:icon name="at-symbol" class="size-5 text-slate-200" />
                        <flux:text class="text-white font-medium">{{ $athlete->email }}</flux:text>
                    </div>
                @endif

                @if ($athlete->height)
                    <div class="flex items-center space-x-2">
                        <flux:icon name="arrows-up-down" class="size-5 text-slate-200" />
                        <flux:text class="text-white">{{ $athlete->height }} cm</flux:text>
                    </div>
                @endif

                @if ($athlete->weight)
                    <div class="flex items-center space-x-2">
                        <flux:icon name="scale" class="size-5 text-slate-200" />
                        <flux:text class="text-white">{{ $athlete->weight }} kg</flux:text>
                    </div>
                @endif
            </div>
        </div>
    </flux:card>

    <flux:separator variant="subtle" class="my-6" />

    {{-- Section Alertes --}}
    <flux:card class="my-6 p-6 bg-white dark:bg-zinc-800 shadow-lg rounded-lg">
        <flux:heading size="lg" level="2" class="mb-4 text-center">🔔 Alertes Récentes</flux:heading>
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

        @if ($readinessStatus)
            @php
                $readiness = $readinessStatus;
                $readinessColor = match($readiness['level']) {
                    'green' => 'emerald',
                    'yellow' => 'lime',
                    'orange' => 'amber',
                    'red' => 'rose',
                    default => 'zinc'
                };
                $readinessBgColor = match($readiness['level']) {
                    'green' => 'bg-emerald-50/50 dark:bg-emerald-950/50',
                    'yellow' => 'bg-lime-50/50 dark:bg-lime-950/50',
                    'orange' => 'bg-amber-50/50 dark:bg-amber-950/50',
                    'red' => 'bg-rose-50/50 dark:bg-rose-950/50',
                    default => 'bg-zinc-50/50 dark:bg-zinc-950/50'
                };
                $readinessBorderColor = match($readiness['level']) {
                    'green' => 'border-emerald-400',
                    'yellow' => 'border-lime-400',
                    'orange' => 'border-amber-400',
                    'red' => 'border-rose-400',
                    default => 'border-zinc-400'
                };
            @endphp
            <div class="mt-2 p-2 border rounded-md {{ $readinessBorderColor }} {{ $readinessBgColor }}">
                <flux:text class="text-sm font-semibold">Readiness: <span class="font-bold">{{ $readiness['readiness_score'] }}</span></flux:text>
                <flux:badge size="sm" inset="top bottom" color="{{ $readinessColor }}" class="mt-1 whitespace-normal!">
                    {{ $readiness['message'] }}
                </flux:badge>
                <flux:text class="mt-2 text-xs whitespace-normal!">
                    <span class="font-medium">Recommandation:</span> {{ $readiness['recommendation'] }}
                </flux:text>
            </div>
        @endif
    </flux:card>

    <flux:text class="mb-4 mt-8 text-lg font-semibold">📈 Statistiques clés:</flux:text>

    {{-- Section des cartes de métriques individuelles --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 my-4">
        @foreach ($dashboard_metrics_data as $metricTypeKey => $metricData)
            <flux:card class="p-4 transform transition-transform duration-200 hover:scale-105 hover:shadow-xl" size="sm">
                <div class="flex items-center justify-between mb-2">
                        <div>
                            <flux:link class="text-xs font-semibold uppercase text-zinc-500 inline" variant="ghost" href="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete' => $athlete->id, 'metric_type' => $metricTypeKey, 'period' => request()->input('period') ?? null]) }}#metric-chart">{{ $metricData['short_label'] }}</flux:link>
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
                </flux:heading>
                <div class="mb-4">
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

    {{-- Section Graphique des métriques --}}
    <flux:card class="my-6" id="metric-chart">
        <div class="flex items-center justify-between">
            <flux:heading size="lg" level="2">Historique des métriques</flux:heading>
            <form class="flex items-center space-x-2"
                id="chart-filter-form"
                action="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete' => $athlete->id]) }}#metric-chart"
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

    <flux:separator variant="subtle" class="my-8" />

    {{-- Section Tableau de toutes les données métriques brutes --}}
    <flux:card class="my-6 p-6 bg-white dark:bg-zinc-800 shadow-lg rounded-lg">
        <flux:heading size="lg" level="2" class="mb-4 text-center">📋 Données Quotidiennes Détaillées</flux:heading>
        <flux:text class="mb-4 text-zinc-600 dark:text-zinc-400 text-center">
            Explore les entrées de métriques jour par jour.
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
                                tooltip="{!! $metricType->getDescription() !!}"
                                label="{{ $metricType->getDescription() }}"
                                color="gray"
                                size="sm"
                                x-data="{}"
                            />
                        </flux:table.column>
                    @endforeach
                </flux:table.columns>

                <flux:table.rows>
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
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell class="py-4 text-center text-zinc-500" colspan="{{ count($display_table_metric_types) + 2 }}">
                                Aucune entrée de métrique trouvée pour cette période.
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

    <flux:separator variant="subtle" class="my-8" />

    {{-- Section Blessures de l'athlète --}}
    <flux:card class="my-6 p-6 bg-white dark:bg-zinc-800 shadow-lg rounded-lg">
        <flux:heading size="lg" level="2" class="mb-4 text-center">🤕 Blessures de {{ $athlete->first_name }}</flux:heading>
        @if ($athlete->injuries->isEmpty())
            <flux:text class="text-center text-zinc-500 italic">
                Aucune blessure déclarée pour cet athlète.
            </flux:text>
        @else
            <div class="space-y-6">
                @foreach ($athlete->injuries as $injury)
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900">
                                    {{ $injury->injury_type?->getLabel() ?? 'Blessure' }} - {{ $injury->pain_location ?? 'Localisation non spécifiée' }}
                                </h4>
                                <div class="text-sm text-gray-600 mt-1">
                                    <p><strong>Date :</strong> {{ $injury->declaration_date->format('d.m.Y') }}</p>
                                    <p><strong>Intensité :</strong> {{ $injury->pain_intensity ?? 'N/A' }}/10</p>
                                    <p><strong>Statut :</strong>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $injury->status->getColor() }}">
                                            {{ $injury->status->getLabel() }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <a href="{{ route('trainers.injuries.feedback.create', ['hash' => $trainer->hash, 'injury' => $injury->id]) }}"
                               class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Nouveau Feedback
                            </a>
                        </div>

                        {{-- Feedbacks médicaux pour cette blessure --}}
                        @if ($injury->medicalFeedbacks->isNotEmpty())
                            <div class="mt-4">
                                <h5 class="text-sm font-medium text-gray-700 mb-3">Feedbacks médicaux ({{ $injury->medicalFeedbacks->count() }})</h5>
                                <div class="space-y-3">
                                    @foreach ($injury->medicalFeedbacks->sortByDesc('feedback_date') as $feedback)
                                        <div class="bg-white border border-gray-200 rounded-md p-3">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1">
                                                    <div class="flex items-center space-x-2 mb-2">
                                                        <span class="text-sm font-medium text-gray-900">
                                                            {{ $feedback->professional_type->getLabel() }}
                                                        </span>
                                                        <span class="text-xs text-gray-500">
                                                            {{ $feedback->feedback_date->format('d.m.Y') }}
                                                        </span>
                                                        @if ($feedback->reported_by_athlete)
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                                Athlète
                                                            </span>
                                                        @endif
                                                        @if ($feedback->trainer)
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                                {{ $feedback->trainer->name }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    
                                                    @if ($feedback->diagnosis)
                                                        <p class="text-xs text-gray-600 mb-1">
                                                            <strong>Diagnostic :</strong> {{ Str::limit($feedback->diagnosis, 100) }}
                                                        </p>
                                                    @endif
                                                    
                                                    @if ($feedback->training_limitations)
                                                        <p class="text-xs text-gray-600 mb-1">
                                                            <strong>Limitations :</strong> {{ Str::limit($feedback->training_limitations, 100) }}
                                                        </p>
                                                    @endif
                                                    
                                                    @if ($feedback->next_appointment_date)
                                                        <p class="text-xs text-gray-600">
                                                            <strong>Prochain RDV :</strong> {{ $feedback->next_appointment_date->format('d.m.Y') }}
                                                        </p>
                                                    @endif
                                                </div>
                                                <a href="{{ route('trainers.medical-feedbacks.edit', ['hash' => $trainer->hash, 'medicalFeedback' => $feedback->id]) }}"
                                                   class="ml-3 inline-flex items-center px-2 py-1 border border-gray-300 rounded text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                    Modifier
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="mt-4 text-center py-4 bg-white border-2 border-dashed border-gray-300 rounded-lg">
                                <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-600">Aucun feedback médical pour cette blessure</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>

</x-layouts.trainer>
