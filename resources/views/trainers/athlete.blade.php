<x-layouts.trainer :title="'Détail de l\'athlète ' . $athlete->name">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <flux:heading class="mb-4 sm:mb-0"
            size="xl"
            level="1">Détail de l'athlète {{ $athlete->name }}</flux:heading>

        {{-- Sélecteur de période pour l'entraîneur --}}
        <form class="flex items-center space-x-2"
            action="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete' => $athlete->id]) }}"
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

    <flux:text class="mb-6 mt-2 text-base">
        Voici le tableau de bord détaillé de {{ $athlete->name }}. Les statistiques sont calculées sur les
        <strong>{{ $period_options[$period_label] ?? 'données sélectionnées' }}</strong>.
    </flux:text>

    {{-- Section Profil Athlète (compact, refonte punchy) --}}
    <flux:card class="relative mb-6 overflow-hidden rounded-lg bg-gradient-to-br from-slate-600 to-slate-700 p-4 text-white shadow-lg">
        {{-- Effet de fond subtil --}}
        <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cucG9ydC5vcmcvMjAwMC9zdmciPjxnIGZpbGw9Im5vbmUiIGZpbGwtcnVsZT0iZXZlbm9kZCI+PHBhdGggZmlsbD0iI0ZGRkZGRiIgZD0iTTAgMGg2MHY2MEgweiIvPjxwYXRoIGZpbGw9IiMwMDAwMDAiIGQ9Ik0wIDBoMzB2MzBIMHoiIG9wYWNpdHk9Ii4wNSIvPjxwYXRoIGZpbGw9IiMwMDAwMDAiIGQ9Ik0zMCAzMGg2MHY2MEgzMHoiIG9wYWNpdHk9Ii4xIi8+PC9nPg==');"></div>

        <div class="relative z-10">
            <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <flux:heading class="mb-2 text-white sm:mb-0"
                    size="lg"
                    level="2">
                    {{ $athlete->name }}
                </flux:heading>
                <div class="flex items-center space-x-2 text-sm">
                    @if ($athlete->last_connection)
                        <flux:icon class="size-4 text-white/80" name="clock" />
                        <flux:text class="text-white/80">
                            Connecté {{ $athlete->last_connection->timezone('Europe/Zurich')->locale('fr_CH')->diffForHumans() }}
                        </flux:text>
                    @else
                        <flux:icon class="size-4 text-white/80" name="clock" />
                        <flux:text class="text-white/80">Jamais connecté</flux:text>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">

                @if ($athlete->birthdate)
                    <div class="flex items-center space-x-2">
                        <flux:icon class="size-5 text-slate-200" name="cake" />
                        <flux:text class="text-white">
                            {{ \Carbon\Carbon::parse($athlete->birthdate)->locale('fr_CH')->isoFormat('L') }}
                        </flux:text>
                    </div>
                @endif

                @if ($athlete->gender->value)
                    <div class="flex items-center space-x-2">
                        @if ($athlete->gender->value === 'm')
                            <flux:icon class="size-5 text-slate-200" name="user" />
                            <flux:text class="text-white">Homme</flux:text>
                        @else
                            <flux:icon class="size-5 text-slate-200" name="user" />
                            <flux:text class="text-white">Femme</flux:text>
                        @endif
                    </div>
                @endif

                @if ($athlete->email)
                    <div class="flex items-center space-x-2">
                        <flux:icon class="size-5 text-slate-200" name="at-symbol" />
                        <flux:text class="font-medium text-white">{{ $athlete->email }}</flux:text>
                    </div>
                @endif

                @if ($athlete->height)
                    <div class="flex items-center space-x-2">
                        <flux:icon class="size-5 text-slate-200" name="arrows-up-down" />
                        <flux:text class="text-white">{{ $athlete->height }} cm</flux:text>
                    </div>
                @endif

                @if ($athlete->weight)
                    <div class="flex items-center space-x-2">
                        <flux:icon class="size-5 text-slate-200" name="scale" />
                        <flux:text class="text-white">{{ $athlete->weight }} kg</flux:text>
                    </div>
                @endif
            </div>
        </div>
    </flux:card>

    <flux:separator class="my-6" variant="subtle" />

    {{-- Section Alertes --}}
    <flux:card class="my-6 rounded-lg bg-white p-6 shadow-lg dark:bg-zinc-800">
        <flux:heading class="mb-4 text-center"
            size="lg"
            level="2">🔔 Alertes Récentes</flux:heading>
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

        @if ($readinessStatus)
            @php
                $readiness = $readinessStatus;
                $readinessColor = match ($readiness['level']) {
                    'green' => 'emerald',
                    'yellow' => 'lime',
                    'orange' => 'amber',
                    'red' => 'rose',
                    default => 'zinc',
                };
                $readinessBgColor = match ($readiness['level']) {
                    'green' => 'bg-emerald-50/50 dark:bg-emerald-950/50',
                    'yellow' => 'bg-lime-50/50 dark:bg-lime-950/50',
                    'orange' => 'bg-amber-50/50 dark:bg-amber-950/50',
                    'red' => 'bg-rose-50/50 dark:bg-rose-950/50',
                    default => 'bg-zinc-50/50 dark:bg-zinc-950/50',
                };
                $readinessBorderColor = match ($readiness['level']) {
                    'green' => 'border-emerald-400',
                    'yellow' => 'border-lime-400',
                    'orange' => 'border-amber-400',
                    'red' => 'border-rose-400',
                    default => 'border-zinc-400',
                };
            @endphp
            <div class="{{ $readinessBorderColor }} {{ $readinessBgColor }} mt-2 rounded-md border p-2">
                <flux:text class="text-sm font-semibold">Readiness: <span class="font-bold">{{ $readiness['readiness_score'] }}</span></flux:text>
                <flux:badge class="whitespace-normal! mt-1"
                    size="sm"
                    inset="top bottom"
                    color="{{ $readinessColor }}">
                    {{ $readiness['message'] }}
                </flux:badge>
                <flux:text class="whitespace-normal! mt-2 text-xs">
                    <span class="font-medium">Recommandation:</span> {{ $readiness['recommendation'] }}
                </flux:text>
            </div>
        @endif
    </flux:card>

    <flux:text class="mb-4 mt-8 text-lg font-semibold">📈 Statistiques clés:</flux:text>

    {{-- Section des cartes de métriques individuelles --}}
    <div class="my-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($dashboard_metrics_data as $metricTypeKey => $metricData)
            <flux:card class="transform p-4 transition-transform duration-200 hover:scale-105 hover:shadow-xl" size="sm">
                <div class="mb-2 flex items-center justify-between">
                    <div>
                        <flux:link class="inline text-xs font-semibold uppercase text-zinc-500"
                            href="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete' => $athlete->id, 'metric_type' => $metricTypeKey, 'period' => request()->input('period') ?? null]) }}#metric-chart"
                            variant="ghost">{{ $metricData['short_label'] }}</flux:link>
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
                </flux:heading>
                <div class="mb-4">
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
                <flux:chart class="h-64" :value="$chart_data['labels_and_data']">
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

    <flux:separator class="my-8" variant="subtle" />

    {{-- Section Tableau de toutes les données métriques brutes --}}
    <flux:card class="my-6 rounded-lg bg-white p-6 shadow-lg dark:bg-zinc-800">
        <flux:heading class="mb-4 text-center"
            size="lg"
            level="2">📋 Données Quotidiennes Détaillées</flux:heading>
        <flux:text class="mb-4 text-center text-zinc-600 dark:text-zinc-400">
            Explore les entrées de métriques jour par jour.
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
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($daily_metrics_grouped_by_date as $date => $rowData)
                        <flux:table.row>
                            <flux:table.cell class="z-1 sticky left-0 bg-white font-semibold dark:bg-zinc-900">
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

    <flux:separator class="my-8" variant="subtle" />

    {{-- Section Blessures de l'athlète --}}
    <flux:card class="my-6 rounded-lg bg-white p-6 shadow-lg dark:bg-zinc-800">
        <flux:heading class="mb-4 text-center"
            size="lg"
            level="2">🤕 Blessures de {{ $athlete->first_name }}</flux:heading>
        @if ($athlete->injuries->isEmpty())
            <flux:text class="text-center italic text-zinc-500">
                Aucune blessure déclarée pour cet athlète.
            </flux:text>
        @else
            <div class="space-y-6">
                @foreach ($athlete->injuries as $injury)
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900">
                                    {{ $injury->injury_type?->getPrefixForLocation() ?? 'Blessure' }} - {{ $injury->pain_location?->getLabel() ?? 'Localisation non spécifiée' }}
                                </h4>
                                <div class="mt-1 text-sm text-gray-600">
                                    <p><strong>Date :</strong> {{ $injury->declaration_date->format('d.m.Y') }}</p>
                                    <p><strong>Intensité :</strong> {{ $injury->pain_intensity ?? 'n/a' }}/10</p>
                                    <p><strong>Statut :</strong>
                                        <span class="{{ $injury->status->getColor() }} inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                                            {{ $injury->status->getLabel() }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <a class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-3 py-2 text-sm font-medium leading-4 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" href="{{ route('trainers.injuries.feedback.create', ['hash' => $trainer->hash, 'injury' => $injury->id]) }}">
                                <svg class="mr-1 h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                Nouveau Feedback
                            </a>
                        </div>

                        {{-- Feedbacks médicaux pour cette blessure --}}
                        @if ($injury->healthEvents->isNotEmpty())
                            <div class="mt-4">
                                <h5 class="mb-3 text-sm font-medium text-gray-700">Feedbacks médicaux ({{ $injury->healthEvents->count() }})</h5>
                                <div class="space-y-3">
                                    @foreach ($injury->healthEvents->sortByDesc('feedback_date') as $feedback)
                                        <div class="rounded-md border border-gray-200 bg-white p-3">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <div class="mb-2 flex items-center space-x-2">
                                                        <span class="text-sm font-medium text-gray-900">
                                                            {{ $feedback->professional_type->getLabel() }}
                                                        </span>
                                                        <span class="text-xs text-gray-500">
                                                            {{ $feedback->feedback_date->format('d.m.Y') }}
                                                        </span>
                                                        @if ($feedback->reported_by_athlete)
                                                            <span class="inline-flex items-center rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                                                Athlète
                                                            </span>
                                                        @endif
                                                        @if ($feedback->trainer)
                                                            <span class="inline-flex items-center rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                                                                {{ $feedback->trainer->name }}
                                                            </span>
                                                        @endif
                                                    </div>

                                                    @if ($feedback->diagnosis)
                                                        <p class="mb-1 text-xs text-gray-600">
                                                            <strong>Diagnostic :</strong> {{ Str::limit($feedback->diagnosis, 100) }}
                                                        </p>
                                                    @endif

                                                    @if ($feedback->training_limitations)
                                                        <p class="mb-1 text-xs text-gray-600">
                                                            <strong>Limitations :</strong> {{ Str::limit($feedback->training_limitations, 100) }}
                                                        </p>
                                                    @endif

                                                    @if ($feedback->next_appointment_date)
                                                        <p class="text-xs text-gray-600">
                                                            <strong>Prochain RDV :</strong> {{ $feedback->next_appointment_date->format('d.m.Y') }}
                                                        </p>
                                                    @endif
                                                </div>
                                                <a class="ml-3 inline-flex items-center rounded border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" href="{{ route('trainers.medical-feedbacks.edit', ['hash' => $trainer->hash, 'medicalFeedback' => $feedback->id]) }}">
                                                    <svg class="mr-1 h-3 w-3"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    Modifier
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="mt-4 rounded-lg border-2 border-dashed border-gray-300 bg-white py-4 text-center">
                                <svg class="mx-auto h-8 w-8 text-gray-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
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
