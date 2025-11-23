<x-layouts.athlete :title="$athlete->name">
    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl" level="1">{{ $athlete->first_name }}</flux:heading>
    </div>

    {{-- Raccourcis rapides --}}
    <div class="my-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
        {{-- Ajouter des métriques quotidiennes --}}
        <a href="{{ route('athletes.metrics.daily.form', ['hash' => $athlete->hash]) }}" aria-label="Ajouter des métriques quotidiennes">
            <flux:card class="bg-lime-50! border-lime-400! flex flex-col items-center justify-center rounded-lg border p-4 text-center shadow-sm transition-all duration-200 ease-in-out hover:bg-lime-100! hover:shadow-md dark:border-lime-800! dark:bg-lime-900/50! dark:hover:bg-lime-800/50!">
                <flux:icon class="mb-2 h-8 w-8 text-lime-600 dark:text-lime-400"
                    name="plus-circle"
                    variant="outline" />
                <flux:text class="font-semibold text-lime-800 dark:text-lime-200">Métriques</flux:text>
            </flux:card>
        </a>

        {{-- Ajouter une séance --}}
        <a href="{{ route('athletes.health-events.create', ['hash' => $athlete->hash]) }}" aria-label="Ajouter un healthEvent">
            <flux:card class="bg-purple-50! border-purple-400! flex flex-col items-center justify-center rounded-lg border p-4 text-center shadow-sm transition-all duration-200 ease-in-out hover:bg-purple-100! hover:shadow-md dark:border-purple-800! dark:bg-purple-900/50! dark:hover:bg-purple-800/50!">
                <flux:icon class="mb-2 h-8 w-8 text-purple-600 dark:text-purple-400"
                    name="stethoscope"
                    variant="outline" />
                <flux:text class="font-semibold text-purple-800 dark:text-purple-200">Séance</flux:text>
            </flux:card>
        </a>

        {{-- Journal --}}
        <a href="{{ route('athletes.journal', ['hash' => $athlete->hash]) }}" aria-label="Journal des feedbacks">
            <flux:card class="bg-sky-50! border-sky-400! flex flex-col items-center justify-center rounded-lg border p-4 text-center shadow-sm transition-all duration-200 ease-in-out hover:bg-sky-100! hover:shadow-md dark:border-sky-800! dark:bg-sky-900/50! dark:hover:bg-sky-800/50!">
                <flux:icon class="mb-2 h-8 w-8 text-sky-600 dark:text-sky-400"
                    name="book-open"
                    variant="outline" />
                <flux:text class="font-semibold text-sky-800 dark:text-sky-200">Journal</flux:text>
            </flux:card>
        </a>

        {{-- Préférences --}}
        <a href="{{ route('athletes.settings', ['hash' => $athlete->hash]) }}" aria-label="Préférences">
            <flux:card class="bg-zinc-50! border-zinc-400! flex flex-col items-center justify-center rounded-lg border p-4 text-center shadow-sm transition-all duration-200 ease-in-out hover:bg-zinc-100! hover:shadow-md dark:border-zinc-800! dark:bg-zinc-900/50! dark:hover:bg-zinc-800/50!">
                <flux:icon class="mb-2 h-8 w-8 text-zinc-600 dark:text-zinc-400"
                    name="cog-6-tooth"
                    variant="outline" />
                <flux:text class="font-semibold text-zinc-800 dark:text-zinc-200">Préférences</flux:text>
            </flux:card>
        </a>
    </div>

    <flux:separator class="mb-4" variant="subtle" />
    <flux:heading class="text-base">
        Aujourd'hui
        <a class="underline"
                href="{{ route('athletes.metrics.daily.form', ['hash' => $athlete->hash]) }}"
                aria-label="Ajouter une métrique">
            <flux:icon class="inline size-5"
                    name="plus-circle"
                    variant="outline" />
                </a>
    </flux:heading>

    {{-- Section Aujourd'hui --}}
    @if ($todayDailyMetrics)
        <div class="mb-4 mt-2 flex flex-wrap gap-1">
            @foreach ($todayDailyMetrics['metrics'] as $metricType => $metricValue)
                @php
                    $metricTypeEnum = \App\Enums\MetricType::from($metricType);
                @endphp
                @if ($metricValue != 'n/a')
                    <flux:tooltip content="{{ $metricTypeEnum->getLabel() }}" x-data="{}">
                        <flux:badge size="sm" color="{{ $metricTypeEnum->getColor() }}">
                            <span class="{{ $metricTypeEnum->getIconifyTailwind() }} me-1 size-4"></span>
                            {{ $metricValue }}
                        </flux:badge>
                    </flux:tooltip>
                @endif
            @endforeach
        </div>
    @else
        <flux:text class="mb-4 mt-2 text-xs text-zinc-500 dark:text-zinc-400">
            Pas encore de données.
            <a class="underline"
                href="{{ route('athletes.metrics.daily.form', ['hash' => $athlete->hash]) }}"
                aria-label="Ajouter une métrique">Ajouter</a>.
        </flux:text>
    @endif

    @if ($readinessStatus)
        <x-readiness-status-card :readiness-status="$readinessStatus" />
    @endif

    {{-- Feedbacks --}}
    @if ($today_feedbacks)
        <div class="mt-2 flex flex-col gap-1">
            @foreach ($today_feedbacks as $feedback)
                <flux:callout class="p-0!"
                    :icon="$feedback->author_type === 'trainer' ? 'user-circle' : 'document-text'"
                    :color="$feedback->author_type === 'trainer' ? 'teal' : 'stone'">
                    <flux:callout.text class="text-xs">{!! nl2br(e($feedback->content)) !!}</flux:callout.text>
                </flux:callout>
            @endforeach
        </div>
    @endif

    {{-- Alertes --}}
    @if (!empty($alerts))
    <div class="mt-2">
        @if (!empty($alerts))
            <div class="flex flex-col gap-3">
                @foreach ($alerts as $alert)
                    <flux:badge class="whitespace-normal! w-full"
                        size="sm"
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
            <flux:text class="text-center italic text-zinc-500 dark:text-zinc-400">
                Aucune alerte détectée pour la période sélectionnée. Tout semble en ordre ! 🎉
            </flux:text>
        @endif
    </div>
    @endif

    {{-- Cycle menstruel --}}
    @if ($athlete->gender->value === 'w' && $menstrualCycleInfo)
    <div class="mt-2">
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
            <div class="{{ $menstrualCycleBoxBorderColor }} {{ $menstrualCycleBoxBgColor }} mt-4 rounded-md border p-3 dark:text-zinc-200">
                <flux:text class="text-sm font-semibold dark:text-zinc-200">Cycle Menstruel:</flux:text>
                <flux:text class="text-xs dark:text-zinc-400">
                    Phase: <span class="font-medium">{{ $menstrualCycleInfo['phase'] }}</span><br>
                    Jours dans la phase: <span class="font-medium">{{ intval($menstrualCycleInfo['days_in_phase']) ?? 'n/a' }}</span><br>
                    Longueur moy. cycle: <span class="font-medium">{{ $menstrualCycleInfo['cycle_length_avg'] ?? 'n/a' }} jours</span>
                    @if ($menstrualCycleInfo['last_period_start'])
                        <br>Dernières règles: <span class="font-medium">{{ \Carbon\Carbon::parse($menstrualCycleInfo['last_period_start'])->locale('fr_CH')->isoFormat('L') }}</span>
                    @endif
                </flux:text>
            </div>
    </div>
    @endif

    <flux:separator class="my-4" variant="subtle" />
    <flux:heading class="text-base">Cette semaine</flux:heading>

    {{-- Section Volume et Intensité Planifiés de la semaine en cours --}}
    @if ($weekly_planned_volume || $weekly_planned_intensity)
        <div class="mb-4 mt-2 grid grid-cols-2 gap-4 sm:grid-cols-2">
            <flux:callout color="blue">
                <flux:callout.heading>Volume</flux:callout.heading>
                <flux:callout.text class="text-2xl! font-bold">
                    {{ number_format($weekly_planned_volume, 0) }}<span class="text-base font-normal">/5</span>
                </flux:callout.text>
            </flux:callout>

            <flux:callout color="sky">
                <flux:callout.heading>Intensité</flux:callout.heading>
                <flux:callout.text class="text-2xl! font-bold">
                    {{ number_format($weekly_planned_intensity, 0) }}<span class="text-base font-normal">/100</span>
                </flux:callout.text>
            </flux:callout>
        </div>
    @endif

    @if ($last_days_feedbacks)
        <div class="mb-4 mt-2 flex flex-col gap-1">
            @foreach ($last_days_feedbacks as $feedback)
                <flux:callout class="p-0!"
                    :icon="$feedback->author_type === 'trainer' ? 'user-circle' : 'document-text'"
                    :color="$feedback->author_type === 'trainer' ? 'teal' : 'stone'">
                    <flux:callout.heading class="text-xs">{{ $feedback->date->locale('fr_CH')->isoFormat('L') }}</flux:callout.heading>
                    <flux:callout.text class="text-xs">{!! nl2br(e($feedback->content)) !!}</flux:callout.text>
                </flux:callout>
            @endforeach
        </div>
    @endif

    {{-- Section Protocoles de Récupération --}}
    <flux:separator class="mb-4 mt-6" variant="subtle" />
    <flux:heading class="text-base">Séances (physio, massage, récupération)</flux:heading>

    <div class="mt-4">
        <a href="{{ route('athletes.health-events.create', ['hash' => $athlete->hash]) }}" aria-label="Ajouter une séance">
            <flux:card class="bg-lime-50! border-lime-400! my-4 hover:bg-zinc-50! dark:border-lime-800! dark:bg-lime-900/50! dark:hover:bg-lime-800/50!"
                size="sm"
                color="lime">
                <flux:heading class="flex items-center gap-2 text-lime-800">Ajouter une séance
                    <flux:icon class="ml-auto text-lime-600"
                        name="plus"
                        variant="micro" />
                </flux:heading>
            </flux:card>
        </a>
        @if ($healthEvents->isEmpty())
            <flux:callout icon="chat-bubble-left-right">
                <flux:callout.heading>Aucune séance enregistrée.</flux:callout.heading>

                <flux:callout.text>
                    Enregistre tes séances de récupération pour les suivre ici.
                </flux:callout.text>
            </flux:callout>
        @else
            <div class="space-y-4">
                @foreach ($healthEvents as $healthEvent)
                    <a class="block rounded-lg border border-gray-200 p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900" href="{{ route('athletes.health-events.edit', ['hash' => $athlete->hash, 'healthEvent' => $healthEvent]) }}">
                        <div class="mb-3 flex items-start justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-zinc-100">
                                    {{ $healthEvent->type->getLabel() }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-zinc-400">
                                    {{ $healthEvent->date->format('d.m.Y') }}
                                    -
                                    {{ str($healthEvent->purpose)->limit(50) }}
                                    @if ($healthEvent->duration_minutes)
                                        • {{ $healthEvent->duration_minutes }} minutes
                                    @endif
                                    @if ($healthEvent->injury)
                                        • Lié à la blessure: {{ $healthEvent->injury->injury_type?->getPrefixForLocation() }} - {{ $healthEvent->injury->pain_location?->getLabel() }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center space-x-2">
                                @if ($healthEvent->effect_on_pain_intensity)
                                    <flux:badge class="whitespace-normal! w-full"
                                        size="sm"
                                        inset="top bottom"
                                        color="blue">
                                        {{ $healthEvent->effect_on_pain_intensity }}/10
                                    </flux:badge>
                                @endif
                                @if ($healthEvent->effectiveness_rating)
                                    <flux:badge class="whitespace-normal! w-full"
                                        size="sm"
                                        inset="top bottom"
                                        color="green">
                                        {{ $healthEvent->effectiveness_rating }}/5
                                    </flux:badge>
                                @endif
                            </div>
                        </div>

                        @if ($healthEvent->note)
                            <div class="mb-3">
                                <p class="mb-1 text-xs font-medium text-gray-700 dark:text-zinc-300">Notes :</p>
                                <p class="rounded bg-gray-50 p-2 text-sm text-gray-800 dark:bg-zinc-700 dark:text-zinc-200">{{ $healthEvent->note }}</p>
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <flux:separator class="my-8" variant="subtle" />

    <flux:heading level="2" size="md">Rapports d'analyse</flux:heading>

    @foreach ($reports as $reportType => $report)
        <div class="mb-8">
            <flux:heading class="mb-4" level="3" size="lg">
                {{ match($reportType) {
                    'daily' => 'Quotidien',
                    'weekly' => 'Hebdomadaire',
                    'monthly' => 'Mensuel',
                    'biannual' => 'Semestriel',
                    default => 'Inconnu',
                } }}
            </flux:heading>

            <div class="my-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                @foreach ($report['sections'] as $section)
                    @if ($section)
                        <x-report-card :section="$section" />
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach

    <div>
        <x-flux::button tag="a" href="{{ route('athletes.reports.show', ['hash' => $athlete->hash]) }}" variant="primary" class="mb-2 mx-auto block text-center w-full" outlined="true" icon="document-chart-bar">Voir le rapport complet</x-flux::button>
        <x-flux::button tag="a" href="{{ route('athletes.reports.ai', ['hash' => $athlete->hash]) }}" variant="primary" color="blue" class="mx-auto block text-center w-full" outlined="true" icon="chart-bar-square">Analyse par l'IA</x-flux::button>
    </div>

    {{-- Section Gamification --}}
    <flux:separator class="mb-4 mt-6" variant="subtle" />

    @if ($gamificationData)
        <div class="mt-2 mb-4">
            <x-gamification-card :gamification-data="$gamificationData" />
        </div>
    @endif

</x-layouts.athlete>
