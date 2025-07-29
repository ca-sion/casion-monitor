<x-layouts.trainer :title="$trainer->name">
    <flux:heading size="xl" level="1">Bonjour {{ $trainer->first_name }}</flux:heading>

    <flux:text class="mb-6 mt-2 text-base">
        Ce tableau de bord présente les métriques de vos athlètes.
        Les tendances et graphiques sont calculés sur les
        <strong>{{ $period_options[$period_label] ?? 'données sélectionnées' }}</strong>.
    </flux:text>

    {{-- Sélecteur de période et options d'affichage --}}
    <form class="my-4 flex flex-wrap items-center gap-4"
        id="dashboard-filter-form"
        action="{{ route('trainers.dashboard', ['hash' => $trainer->hash]) }}"
        method="GET">
        <div class="flex items-center space-x-2">
            <flux:text class="whitespace-nowrap text-base">Voir les données des:</flux:text>
            <flux:select name="period" onchange="document.getElementById('dashboard-filter-form').submit()">
                @foreach ($period_options as $value => $label)
                    <option value="{{ $value }}" @selected($period_label === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex items-center space-x-2">
            <input id="hidden_show_info_alerts"
                name="show_info_alerts"
                type="hidden"
                value="{{ $show_info_alerts ? '1' : '0' }}">
            <flux:field variant="inline">
                <flux:label><span class="icon-[material-symbols-light--chat-info] size-5"></span></flux:label>
                <flux:switch id="show_info_alerts_switch"
                    :checked="$show_info_alerts"
                    onchange="document.getElementById('hidden_show_info_alerts').value = this.checked ? '1' : '0'; document.getElementById('dashboard-filter-form').submit();" />
            </flux:field>
        </div>

        <div class="flex items-center space-x-2">
            <input id="hidden_show_menstrual_cycle"
                name="show_menstrual_cycle"
                type="hidden"
                value="{{ $show_menstrual_cycle ? '1' : '0' }}">
            <flux:field variant="inline">
                <flux:label><span class="icon-[material-symbols-light--menstrual-health] size-5"></span></flux:label>
                <flux:switch id="show_menstrual_cycle_switch"
                    :checked="$show_menstrual_cycle"
                    onchange="document.getElementById('hidden_show_menstrual_cycle').value = this.checked ? '1' : '0'; document.getElementById('dashboard-filter-form').submit();" />
            </flux:field>
        </div>

        <div class="flex items-center space-x-2">
            <input id="hidden_show_chart_and_avg"
                name="show_chart_and_avg"
                type="hidden"
                value="{{ $show_chart_and_avg ? '1' : '0' }}">
            <flux:field variant="inline">
                <flux:label><span class="icon-[material-symbols-light--table-chart-view] size-5"></span></flux:label>
                <flux:switch id="show_chart_and_avg_switch"
                    :checked="$show_chart_and_avg"
                    onchange="document.getElementById('hidden_show_chart_and_avg').value = this.checked ? '1' : '0'; document.getElementById('dashboard-filter-form').submit();" />
            </flux:field>
        </div>
    </form>

    <flux:separator variant="subtle" />

    <flux:table class="my-4">
        <flux:table.columns>
            <flux:table.column class="z-1 sticky left-0 max-w-36 bg-white dark:bg-zinc-900">Athlète</flux:table.column>
            <flux:table.column class="max-w-48 text-center">Readiness</flux:table.column>
            @if ($has_alerts || $show_menstrual_cycle)
            <flux:table.column class="max-w-48 text-center">Alertes & Cycle</flux:table.column>
            @endif

            {{-- Colonnes pour les métriques calculées (générées par boucle) --}}
            @foreach ($calculated_metric_types as $metric)
                <flux:table.column class="w-fit text-center">
                    {{ $metric->getLabelShort() }}
                    <x-filament::icon-button class="ms-1 inline"
                        icon="heroicon-o-information-circle"
                        tooltip="{!! $metric->getDescription() !!}"
                        label="{{ $metric->getDescription() }}"
                        color="gray"
                        size="sm"
                        x-data="{}" />
                </flux:table.column>
            @endforeach

            {{-- Colonnes pour les métriques de la base de données (générées par boucle) --}}
            @foreach ($dashboard_metric_types as $metricType)
                <flux:table.column class="w-fit text-center">
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
            <flux:table.column class="w-36 text-center">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($athletes_overview_data as $athlete)
                <flux:table.row>
                    <flux:table.cell class="z-1 sticky left-0 bg-white dark:bg-zinc-900">
                        <a href="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete' => $athlete->id]) }}">
                            <div class="flex flex-col items-start gap-0">
                                <div>{{ $athlete->first_name }}</div>
                                <div>{{ $athlete->last_name }}</div>
                            </div>
                            <div class="mt-2 text-xs">
                                <x-filament::link class="color-zinc-500! font-normal"
                                    icon="heroicon-o-clock"
                                    tooltip="Dernière connexion"
                                    label="Dernière connexion"
                                    color="gray"
                                    size="xs"
                                    x-data="{}">
                                    {{ $athlete->last_connection ? $athlete->last_connection->timezone('Europe/Zurich')->locale('fr_CH')->shortAbsoluteDiffForHumans() : 'Jamais' }}
                                </x-filament::link>
                            </div>
                        </a>
                    </flux:table.cell>

                    {{-- Cellule Readiness --}}
                    <flux:table.cell>
                        <div class="flex w-48 flex-col gap-2">

                            {{-- Statut de Readiness --}}
                            @if ($athlete->readiness_status)
                                @php
                                    $readiness = $athlete->readiness_status;
                                    $readinessColor = match ($readiness['level']) {
                                        'green' => 'emerald',
                                        'yellow' => 'lime',
                                        'orange' => 'amber',
                                        'red' => 'rose',
                                        'neutral' => 'zinc',
                                        default => 'zinc',
                                    };
                                    $readinessBgColor = match ($readiness['level']) {
                                        'green' => 'bg-emerald-50/50 dark:bg-emerald-950/50',
                                        'yellow' => 'bg-lime-50/50 dark:bg-lime-950/50',
                                        'orange' => 'bg-amber-50/50 dark:bg-amber-950/50',
                                        'red' => 'bg-rose-50/50 dark:bg-rose-950/50',
                                        'neutral' => 'bg-zinc-50/50 dark:bg-zinc-950/50',
                                        default => 'bg-zinc-50/50 dark:bg-zinc-950/50',
                                    };
                                    $readinessBorderColor = match ($readiness['level']) {
                                        'green' => 'border-emerald-400',
                                        'yellow' => 'border-lime-400',
                                        'orange' => 'border-amber-400',
                                        'red' => 'border-rose-400',
                                        'neutral' => 'border-zinc-400',
                                        default => 'border-zinc-400',
                                    };
                                @endphp
                                <div class="{{ $readinessBorderColor }} {{ $readinessBgColor }} rounded-md border p-2">
                                    <flux:text class="text-sm font-semibold">Readiness: <span class="font-bold">{{ $readiness['readiness_score'] }}</span></flux:text>
                                    <flux:badge class="whitespace-normal! mt-1"
                                        size="sm"
                                        inset="top bottom"
                                        color="{{ $readinessColor }}">
                                        {{ $readiness['message'] }}
                                    </flux:badge>
                                    @php
                                        $truncateLength = 5;
                                        $needsTruncation = strlen($readiness['recommendation']) > $truncateLength;
                                    @endphp
                                    <div x-data="{ expanded: false }">
                                        <flux:text class="whitespace-normal! mt-2 text-xs">
                                            <span class="font-medium">Recommandation:</span>
                                            <span x-show="!expanded" x-cloak>
                                                {{ Str::limit($readiness['recommendation'], $truncateLength, '…') }}
                                            </span>

                                            <span x-show="expanded"
                                                x-cloak>
                                                {{ $readiness['recommendation'] }}
                                            </span>

                                            @if ($needsTruncation)
                                            <span class="ms-2">
                                                <flux:link class="text-xs"
                                                    href="javascript:void(0)"
                                                    variant="subtle"
                                                    @click="expanded = !expanded"
                                                    x-text="expanded ? '-' : '+'">
                                                </flux:link>
                                            </span>
                                            @endif
                                        </flux:text>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </flux:table.cell>

                    @if ($has_alerts || $show_menstrual_cycle)
                    {{-- Cellule Alertes & Cycle --}}
                    <flux:table.cell>
                        <div class="flex w-48 flex-col gap-2">
                            @if ($has_alerts)
                            {{-- Alertes --}}
                            <div class="flex flex-col gap-2">
                                @foreach (array_merge($athlete->alerts) as $alert)
                                    @if ($show_info_alerts || $alert['type'] !== 'info')
                                        <div>
                                            <flux:badge class="whitespace-normal!"
                                                size="sm"
                                                inset="top bottom"
                                                color="{{ match ($alert['type']) {
                                                    'danger' => 'rose',
                                                    'warning' => 'amber',
                                                    'info' => 'sky',
                                                    'success' => 'emerald',
                                                    default => 'zinc',
                                                } }}">
                                                {{ $alert['message'] }}
                                            </flux:badge>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            @endif

                            {{-- Cycle Menstruel --}}
                            @if ($show_menstrual_cycle && $athlete->menstrual_cycle_info)
                                @php
                                    $info = $athlete->menstrual_cycle_info;
                                    $borderColor = match ($info['phase']) {
                                        'Aménorrhée', 'Oligoménorrhée' => 'border-rose-400',
                                        'Potentiel retard ou cycle long' => 'border-amber-400',
                                        'Inconnue' => 'border-sky-400',
                                        default => 'border-emerald-400',
                                    };
                                    $bgColor = match ($info['phase']) {
                                        'Aménorrhée', 'Oligoménorrhée' => 'bg-rose-50/50 dark:bg-rose-950/50',
                                        'Potentiel retard ou cycle long' => 'bg-amber-50/50 dark:bg-amber-950/50',
                                        'Inconnue' => 'bg-sky-50/50 dark:bg-sky-950/50',
                                        default => 'bg-emerald-50/50 dark:bg-emerald-950/50',
                                    };
                                @endphp
                                <div class="{{ $borderColor }} {{ $bgColor }} rounded-md border p-2">
                                    <flux:text class="text-sm font-semibold">Cycle Menstruel:</flux:text>
                                    <flux:text class="text-xs whitespace-normal!">
                                        Phase: <span class="font-medium">{{ $info['phase'] }}</span><br>
                                        Jours dans la phase: <span class="font-medium">{{ intval($info['days_in_phase']) ?? 'n/a' }}</span><br>
                                        Longueur moy. cycle: <span class="font-medium">{{ $info['cycle_length_avg'] ?? 'n/a' }} jours</span>
                                        @if ($info['last_period_start'])
                                            <br>Dernier J1: <span class="font-medium">{{ $info['last_period_start'] }}</span>
                                        @endif
                                        @if ($info['reason'])
                                            <br><span class="whitespace-normal! text-xs italic text-zinc-500">{{ $info['reason'] }}</span>
                                        @endif
                                    </flux:text>
                                </div>
                            @endif
                        </div>
                    </flux:table.cell>
                    @endif

                    {{-- Boucle pour les cellules de métriques (calculées + brutes) --}}
                    @php
                        $allMetricsToDisplay = collect($calculated_metric_types)->merge(collect($dashboard_metric_types));
                    @endphp

                    @if($show_chart_and_avg)
                    @foreach ($allMetricsToDisplay as $metricInfo)
                        @php
                            $allMetric = collect($athlete->dashboard_metrics_data)->merge($athlete->weekly_metrics_data);
                            $metricData = $allMetric[$metricInfo->value];
                            $chartData = $metricData['chart_data'] ?? ['data' => []];
                        @endphp
                        <flux:table.cell>
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center justify-between">
                                    <flux:badge size="sm" color="{{ $metricInfo->getColor() }}">
                                        <span class="{{ $metricInfo->getIconifyTailwind() }} size-4"></span>
                                    </flux:badge>
                                    <flux:text class="ms-1 font-bold">
                                        @if ($metricData['is_last_value_today'])
                                            <span class="icon-[material-symbols-light--check-small] size-4 align-middle"></span>
                                        @endif
                                        {{ $metricData['formatted_last_value'] }}
                                    </flux:text>
                                </div>
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs text-zinc-600">Moy. 7j:</flux:text>
                                    <flux:text class="ms-1 font-medium">{{ $metricData['formatted_average_7_days'] }}</flux:text>
                                </div>
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs text-zinc-600">Moy. 30j:</flux:text>
                                    <flux:text class="ms-1 font-medium">{{ $metricData['formatted_average_30_days'] }}</flux:text>
                                </div>
                                @if ($metricData['is_numerical'] && $metricData['trend_icon'] && $metricData['trend_percentage'] !== 'n/a')
                                    <div class="mt-1 flex items-center justify-between">
                                        <flux:badge size="sm"
                                            inset="top bottom"
                                            color="{{ $metricData['trend_color'] }}">
                                            <div class="flex items-center gap-1">
                                                <x-filament::icon class="-mr-0.5 shrink-0 [:where(&)]:size-4" name="{{ $metricData['trend_icon'] }}" />
                                                <span>{{ $metricData['trend_percentage'] }}</span>
                                            </div>
                                        </flux:badge>
                                    </div>
                                @else
                                    <flux:text class="mt-1 text-xs font-semibold uppercase text-zinc-500">Tendance: <span class="text-zinc-500 dark:text-zinc-400" title="Tendance inconnue">n/a</span></flux:text>
                                @endif
                            </div>
                            <div class="mt-2">
                                @if ($chartData && !empty($chartData['data']) && count(array_filter($chartData['data'], fn($val) => $val !== null)) >= 2)
                                    <flux:chart class="aspect-[3/1] h-10 w-full" :value="collect($chartData['data']) -> filter(fn($val) => $val !== null) -> take(14)">
                                        <flux:chart.svg gutter="0">
                                            <flux:chart.line class="text-zinc-500 dark:text-zinc-400" />
                                        </flux:chart.svg>
                                    </flux:chart>
                                @else
                                    <flux:card class="flex h-10 items-center border-2 border-dashed">
                                        <flux:text class="text-center text-sm text-zinc-500"> </flux:text>
                                    </flux:card>
                                @endif
                            </div>
                        </flux:table.cell>
                    @endforeach
                    @endif

                    @if(! $show_chart_and_avg)
                    @foreach ($allMetricsToDisplay as $metricInfo)
                        @php
                            $allMetric = collect($athlete->dashboard_metrics_data)->merge($athlete->weekly_metrics_data);
                            $metricData = $allMetric[$metricInfo->value];
                        @endphp
                        <flux:table.cell>
                            <div>
                                <flux:badge size="sm" color="{{ $metricInfo->getColor() }}">
                                    <span class="{{ $metricInfo->getIconifyTailwind() }} size-4 me-1"></span>
                                    {{ $metricData['formatted_last_value'] }}
                                </flux:badge>
                                @if ($metricData['is_last_value_today'])
                                    <span class="icon-[material-symbols-light--check-small] size-4"></span>
                                @endif
                            </div>
                            <div>
                                @if ($metricData['is_numerical'] && $metricData['trend_icon'] && $metricData['trend_percentage'] !== 'n/a')
                                    <div class="mt-2 flex items-center justify-between">
                                        <flux:badge size="sm"
                                            inset="top bottom"
                                            color="{{ $metricData['trend_color'] }}"
                                            style="background-color: transparent;">
                                            <div class="flex items-center gap-1">
                                                <x-filament::icon class="-mr-0.5 shrink-0 [:where(&)]:size-4" name="{{ $metricData['trend_icon'] }}" />
                                                <span>{{ $metricData['trend_percentage'] }}</span>
                                            </div>
                                        </flux:badge>
                                    </div>
                                @else
                                    <flux:text class="mt-2 text-xs font-semibold uppercase text-zinc-500"> </flux:text>
                                @endif
                            </div>
                        </flux:table.cell>
                    @endforeach
                    @endif

                    <flux:table.cell class="text-center">
                        <flux:modal.trigger :name="'copy-link-account-'.$athlete->id">
                            <flux:button icon="user"
                                variant="ghost"
                                size="sm">Compte</flux:button>
                        </flux:modal.trigger>
                        <flux:modal class="md:w-96" :name="'copy-link-account-'.$athlete->id">
                            <div class="space-y-6">
                                <div>
                                    <flux:heading size="lg">Compte de l'athlète</flux:heading>
                                    <flux:text class="mt-2">Partager le lien du compte de l'athlète pour qu'il puisse y accéder.</flux:text>
                                </div>
                                <flux:input value="{{ $athlete->accountLink }}"
                                    disbaled
                                    copyable />
                            </div>
                        </flux:modal>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:separator class="my-2" variant="subtle" />
    <flux:text class="text-sm">La valeur dans chaque colonne indique la dernière valeur enregistrée pour cette métrique.</flux:text>

    <flux:text class="text-sm">La valeur dans les badges indique la tendance des changements d'une métrique spécifique dans une période donnée. Elle examine si la métrique augmente, diminue ou reste stable en comparant les valeurs moyennes du début et de la fin d'un ensemble de données filtrées et triées par date.</flux:text>

</x-layouts.trainer>
