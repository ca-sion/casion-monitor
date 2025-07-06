<x-layouts.trainer :title="$trainer->name">
    <flux:heading size="xl" level="1">Bonjour {{ $trainer->first_name }}</flux:heading>

    <flux:text class="mb-6 mt-2 text-base">
        Ce tableau de bord présente les métriques de vos athlètes.
        Les tendances et graphiques sont calculés sur les
        <strong>{{ $period_options[$period_label] ?? 'données sélectionnées' }}</strong>.
    </flux:text>

    {{-- Sélecteur de période --}}
    <form class="my-4 flex items-center space-x-2"
        action="{{ route('trainers.dashboard', ['hash' => $trainer->hash]) }}"
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

    <flux:separator variant="subtle" />

    <flux:table class="my-4">
        <flux:table.columns>
            <flux:table.column class="z-1 sticky left-0 max-w-36 bg-white dark:bg-zinc-900">Athlète</flux:table.column>
            <flux:table.column class="max-w-36 text-center">Alertes & Cycle</flux:table.column>
            @foreach ($dashboard_metric_types as $metricType)
                <flux:table.column class="w-fit text-center">
                    {{ $metricType->getLabelShort() }}
                    <flux:tooltip content="{{ $metricType->getDescription() }}">
                        <flux:icon class="ms-2 inline size-4"
                            size="sm"
                            icon="information-circle"></flux:icon>
                    </flux:tooltip>
                </flux:table.column>
            @endforeach
            <flux:table.column class="w-36 text-center">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($athletes_overview_data as $athlete)
                <flux:table.row>
                    <flux:table.cell class="z-1 sticky left-0 bg-white dark:bg-zinc-900">
                        <a href="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete' => $athlete->id]) }}">
                            <div class="flex items-start flex-col gap-0">
                                {{-- <flux:avatar src="https://unavatar.io/{{ $athlete->email }}?fallback=https://api.dicebear.com/9.x/lorelei/svg?seed={{ $athlete->first_name }}" class="size-6" /> --}}
                                <div>{{ $athlete->first_name }}</div>
                                <div>{{ $athlete->last_name }}</div>
                            </div>
                            <div class="mt-2 text-xs">
                                <flux:tooltip content="Dernière connexion">
                                    <flux:icon.clock class="inline size-4" size="sm" />
                                </flux:tooltip>
                                {{ $athlete->last_connection ? $athlete->last_connection->timezone('Europe/Zurich')->locale('fr_CH')->diffForHumans() : 'Jamais' }}
                            </div>
                        </a>
                    </flux:table.cell>

                    {{-- Alertes & Cycle --}}
                    <flux:table.cell>
                        <div class="flex flex-col gap-2">
                            {{-- Alertes --}}
                            <div class="flex flex-col gap-2">
                                @foreach ($athlete->alerts as $alert)
                                <div>
                                    <flux:badge size="sm" inset="top bottom" class="whitespace-normal!"
                                        color="{{ match($alert['type']) {
                                            'danger' => 'rose',
                                            'warning' => 'amber',
                                            'info' => 'sky',
                                            'success' => 'emerald',
                                            default => 'zinc'
                                        } }}">
                                        {{ $alert['message'] }}
                                    </flux:badge>
                                </div>
                                @endforeach
                            </div>

                            {{-- Cycle Menstruel (uniquement pour les filles) --}}
                            @if ($athlete->gender === 'w' && $athlete->menstrualCycleInfo)
                                @php
                                    $menstrualCycleBoxBorderColor = 'border-emerald-400';
                                    $menstrualCycleBoxBgColor = 'bg-emerald-50/50 dark:bg-emerald-950/50';

                                    if ($athlete->menstrualCycleInfo['phase'] === 'Aménorrhée' || $athlete->menstrualCycleInfo['phase'] === 'Oligoménorrhée') {
                                        $menstrualCycleBoxBorderColor = 'border-rose-400';
                                        $menstrualCycleBoxBgColor = 'bg-rose-50/50 dark:bg-rose-950/50';
                                    } elseif ($athlete->menstrualCycleInfo['phase'] === 'Potentiel retard ou cycle long') {
                                        $menstrualCycleBoxBorderColor = 'border-amber-400';
                                        $menstrualCycleBoxBgColor = 'bg-amber-50/50 dark:bg-amber-950/50';
                                    } elseif ($athlete->menstrualCycleInfo['phase'] === 'Inconnue') {
                                        $menstrualCycleBoxBorderColor = 'border-sky-400';
                                        $menstrualCycleBoxBgColor = 'bg-sky-50/50 dark:bg-sky-950/50';
                                    }
                                @endphp
                                <div class="p-2 border rounded-md {{ $menstrualCycleBoxBorderColor }} {{ $menstrualCycleBoxBgColor }}">
                                    <flux:text class="text-sm font-semibold">Cycle Menstruel:</flux:text>
                                    <flux:text class="text-xs">
                                        Phase: <span class="font-medium">{{ $athlete->menstrualCycleInfo['phase'] }}</span><br>
                                        Jours dans la phase: <span class="font-medium">{{ intval($athlete->menstrualCycleInfo['days_in_phase']) ?? 'N/A' }}</span><br>
                                        Longueur moy. cycle: <span class="font-medium">{{ $athlete->menstrualCycleInfo['cycle_length_avg'] ?? 'N/A' }} jours</span>
                                        @if($athlete->menstrualCycleInfo['last_period_start'])
                                            <br>Dernier J1: <span class="font-medium">{{ $athlete->menstrualCycleInfo['last_period_start'] }}</span>
                                        @endif
                                        @if($athlete->menstrualCycleInfo['reason'])
                                            <br><span class="text-zinc-500 text-xs italic whitespace-normal!">{{ $athlete->menstrualCycleInfo['reason'] }}</span>
                                        @endif
                                    </flux:text>
                                </div>
                            @endif
                        </div>
                    </flux:table.cell>

                    @foreach ($dashboard_metric_types as $metricType)
                        @php
                            $metricData = $athlete->metricsDataForDashboard[$metricType->value];
                            $chartData = $metricData['chart_data'];
                        @endphp
                        <flux:table.cell>
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center justify-between">
                                    <flux:tooltip content="Dernière valeur enregistrée pour cette métrique sur la période sélectionnée.">
                                        <flux:text class="text-xs font-semibold uppercase text-zinc-500 underline decoration-zinc-500/30 decoration-dotted">Valeur:</flux:text>
                                    </flux:tooltip>
                                    <flux:text class="ms-1 font-bold">{{ $metricData['formatted_last_value'] }}</flux:text>
                                </div>
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs text-zinc-600">Moy. 7j:</flux:text>
                                    <flux:text class="ms-1 font-medium">{{ $metricData['formatted_average_7_days'] }}</flux:text>
                                </div>
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs text-zinc-600">Moy. 30j:</flux:text>
                                    <flux:text class="ms-1 font-medium">{{ $metricData['formatted_average_30_days'] }}</flux:text>
                                </div>
                                {{ dd($metricData) }}
                                @if ($metricData['is_numerical'] && $metricData['trend_icon'] && $metricData['trend_percentage'] !== 'N/A')
                                    <div class="mt-1 flex items-center justify-between">
                                    <flux:badge size="sm"
                                        inset="top bottom"
                                        color="{{ $metricData['trend_color'] }}">
                                        <div class="flex items-center gap-1">
                                            <flux:icon class="-mr-0.5"
                                                name="{{ $metricData['trend_icon'] }}"
                                                variant="micro" />
                                            <span>{{ $metricData['trend_percentage'] }}</span>
                                        </div>
                                    </flux:badge>
                                    </div>
                                @else
                                    <flux:text class="mt-1 text-xs font-semibold uppercase text-zinc-500">Tendance: <span class="text-zinc-500 dark:text-zinc-400" title="Tendance inconnue">N/A</span></flux:text>
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

                    <flux:table.cell class="text-center">
                        <flux:modal.trigger :name="'copy-link-account-'.$athlete->id">
                            <flux:button icon="user" variant="ghost" size="sm">Compte</flux:button>
                        </flux:modal.trigger>
                        <flux:modal :name="'copy-link-account-'.$athlete->id" class="md:w-96">
                            <div class="space-y-6">
                                <div>
                                    <flux:heading size="lg">Compte de l'athlète</flux:heading>
                                    <flux:text class="mt-2">Partager le lien du compte de l'athlète pour qu'il puisse y accéder.</flux:text>
                                </div>
                                <flux:input value="{{ $athlete->accountLink }}" disbaled copyable />
                            </div>
                        </flux:modal>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:separator variant="subtle" class="my-2" />
    <flux:text class="text-sm">La valeur dans les badges indique la tendance des changements d'une métrique spécifique dans une période donnée. Elle examine si la métrique augmente, diminue ou reste stable en comparant les valeurs moyennes du début et de la fin d'un ensemble de données filtrées et triées par date.</flux:text>

</x-layouts.trainer>
