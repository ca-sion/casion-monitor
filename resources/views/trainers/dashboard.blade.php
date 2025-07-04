<x-layouts.trainer :title="$trainer->name">
    <flux:heading size="xl" level="1">Bonjour {{ $trainer->first_name }}</flux:heading>

    <flux:text class="mb-6 mt-2 text-base">
        Ce tableau de bord présente les métriques de vos athlètes.
        Les tendances et graphiques sont calculés sur les
        <strong>{{ $period_options[$period_label] ?? 'données sélectionnées' }}</strong>.
    </flux:text>

    {{-- Sélecteur de période --}}
    <form action="{{ route('trainers.dashboard', ['hash' => $trainer->hash]) }}" method="GET" class="flex items-center space-x-2 my-4">
        <flux:text class="text-base whitespace-nowrap">Voir les données des:</flux:text>
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
            <flux:table.column class="sticky left-0 bg-white dark:bg-zinc-900 z-10 w-48">Athlète</flux:table.column>
            @foreach ($dashboard_metric_types as $metricType)
                <flux:table.column class="text-center w-fit">
                    {{ $metricType->getLabelShort() }}
                    <flux:tooltip content="{{ $metricType->getDescription() }}">
                        <flux:icon size="sm" class="inline size-4 ms-2" icon="information-circle"></flux:icon>
                    </flux:tooltip>
                </flux:table.column>
            @endforeach
            <flux:table.column class="text-center w-36">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($athletes_overview_data as $athlete)
                <flux:table.row>
                    <flux:table.cell class="sticky left-0 bg-white dark:bg-zinc-900 z-48">
                        <div class="flex items-center gap-2">
                            <flux:avatar src="https://unavatar.io/{{ $athlete->id }}?fallback=https://api.dicebear.com/7.x/pixel-art/svg?seed={{ $athlete->id }}" class="size-6" />
                            <span>{{ $athlete->name }}</span>
                        </div>
                        <div class="text-xs mt-1 ms-8">
                            <flux:tooltip content="Dernière connexion">
                                <flux:icon.clock size="sm" class="inline size-4" />
                            </flux:tooltip>
                            {{ $athlete->last_connection ? $athlete->last_connection->timezone('Europe/Zurich')->locale('fr_CH')->diffForHumans() : 'Jamais' }}
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
                                        <flux:text class="text-xs font-semibold uppercase text-zinc-500 underline decoration-dotted decoration-zinc-500/30">Valeur:</flux:text>
                                    </flux:tooltip>
                                    <flux:text class="font-bold ms-1">{{ $metricData['formatted_last_value'] }}</flux:text>
                                </div>
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs text-zinc-600">Moy. 7j:</flux:text>
                                    <flux:text class="font-medium ms-1">{{ $metricData['formatted_average_7_days'] }}</flux:text>
                                </div>
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs text-zinc-600">Moy. 30j:</flux:text>
                                    <flux:text class="font-medium ms-1">{{ $metricData['formatted_average_30_days'] }}</flux:text>
                                </div>
                                @if ($metricData['is_numerical'] && $metricData['trend_icon'] && $metricData['trend_percentage'] !== 'N/A')
                                    <div class="flex items-center justify-between mt-1">
                                        <flux:tooltip content="La tendance compare la moyenne des 7 derniers jours à la moyenne des 30 derniers jours.">
                                            <flux:badge size="sm" inset="top bottom" color="{{ $metricData['trend_color'] }}">
                                            <div class="flex items-center gap-1">
                                                <flux:icon name="{{ $metricData['trend_icon'] }}" variant="micro" class="-mr-0.5" />
                                                <span>{{ $metricData['trend_percentage'] }}</span>
                                            </div>
                                        </flux:badge>
                                        </flux:tooltip>
                                    </div>
                                @else
                                    <flux:text class="text-xs font-semibold uppercase text-zinc-500 mt-1">Tendance: <span class="text-zinc-500 dark:text-zinc-400" title="Tendance inconnue">N/A</span></flux:text>
                                @endif
                            </div>
                            <div class="mt-2">
                                @if ($chartData && !empty($chartData['data']) && count(array_filter($chartData['data'], fn($val) => $val !== null)) >= 2)
                                    <flux:chart class="aspect-[3/1] w-full h-10" :value="collect($chartData['data'])->filter(fn($val) => $val !== null)->take(14)">
                                        <flux:chart.svg gutter="0">
                                            <flux:chart.line class="text-zinc-500 dark:text-zinc-400" />
                                        </flux:chart.svg>
                                    </flux:chart>
                                @else
                                <flux:card class="border-dashed border-2 h-10 flex items-center">
                                    <flux:text class="text-zinc-500 text-sm text-center"> </flux:text>
                                </flux:card>
                                @endif
                            </div>
                        </flux:table.cell>
                    @endforeach
                    <flux:table.cell class="text-center">
                        <flux:link href="{{ $athlete->accountLink }}">Compte</flux:link>
                        <flux:link href="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete' => $athlete->id]) }}">Détail</flux:link>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

</x-layouts.trainer>