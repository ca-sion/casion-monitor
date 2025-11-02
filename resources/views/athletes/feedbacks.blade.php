<x-layouts.athlete :title="$athlete->name">
    <div class="container mx-auto">
        {{-- En-tête --}}
        <div class="px-4 py-6 sm:px-6">
            <flux:heading size="xl" level="1" class="font-extrabold text-gray-900 mb-1">
                Journal d'entraînement
            </flux:heading>
            <flux:text class="text-gray-600 text-md">
                Ton journal pour suivre tes entraînements, tes feedbacks et ton état.
            </flux:text>
        </div>

        {{-- Section des filtres --}}
        <div class="px-4 py-4 sm:px-6 bg-gray-50 border-t border-b border-gray-200">
            <form action="{{ route('athletes.feedbacks', ['hash' => $athlete->hash]) }}" method="GET" class="flex flex-col sm:flex-row gap-4 items-center">
                <div class="w-full sm:w-auto">
                    <label for="period" class="block text-sm font-medium text-gray-700 sr-only">Période</label>
                    <flux:select id="period" name="period" placeholder="Sélectionner une période" class="w-full">
                        @foreach ($periodOptions as $key => $label)
                            <option value="{{ $key }}" @selected($currentPeriod === $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="w-full sm:w-auto">
                    <flux:button type="submit" variant="primary" icon="magnifying-glass" class="w-full justify-center">
                        Appliquer
                    </flux:button>
                </div>
            </form>
        </div>

        {{-- Timeline --}}
        <div class="p-4 sm:p-6">
            @forelse ($groupedItems as $date => $items)
                <div class="relative pl-8 sm:pl-10 py-6 group">
                    {{-- Date et icône sur la ligne de temps --}}
                    <div class="flex items-center mb-4">
                        <div class="absolute left-0 z-10 w-8 h-8 sm:w-10 sm:h-10 bg-gray-600 rounded-full flex items-center justify-center ring-4 ring-white dark:ring-gray-900">
                            <span class="text-white font-bold text-sm">{{ \Carbon\Carbon::parse($date)->isoFormat('DD') }}</span>
                        </div>
                        <flux:heading level="2" size="lg" class="font-bold text-gray-800 ml-4">
                            {{ \Carbon\Carbon::parse($date)->isoFormat('dddd LL') }}
                        </flux:heading>
                        <flux:button
                            href="{{ route('athletes.recovery-protocols.create', ['hash' => $athlete->hash, 'date' => $date]) }}"
                            variant="subtle"
                            icon="plus-circle"
                            class="inline">
                        </flux:button>
                    </div>

                    {{-- Ligne verticale de la timeline --}}
                    <div class="absolute left-4 sm:left-5 top-0 h-full w-0.5 bg-gray-200"></div>

                    {{-- Cartes d'événements --}}
                    <div class="space-y-6">
                        @foreach ($items as $item)
                            @switch($item['type'])
                                @case('feedback')
                                    @php
                                        $feedback = $item['data'];
                                        $isTrainerFeedback = ($feedback->author_type === 'trainer');
                                        $cardBg = $isTrainerFeedback ? 'bg-slate-50!' : 'bg-yellow-50!';
                                        $cardBorder = $isTrainerFeedback ? 'border-slate-300!' : 'border-yellow-400!';
                                        $badgeColor = $isTrainerFeedback ? 'slate' : 'yellow';
                                        $authorText = $isTrainerFeedback ? ($feedback->trainer?->first_name ?? 'Entraîneur') : 'Moi';
                                        $truncateLength = 200;
                                        $needsTruncation = strlen($feedback->content) > $truncateLength;
                                    @endphp
                                    <flux:card class="shadow-lg hover:shadow-xl transition-shadow duration-300 {{ $cardBg }} {{ $cardBorder }} border">
                                        <div class="flex items-center justify-between">
                                            <flux:badge color="{{ $badgeColor }}" size="sm">
                                                {{ $feedback->type->getLabel() }}
                                            </flux:badge>
                                            <flux:text class="text-xs font-semibold">
                                                {{ $authorText }}
                                            </flux:text>
                                        </div>
                                        <div class="text-xs text-gray-500 mb-3 mt-1">
                                            {{ $feedback->created_at->format('d.m.Y à H:i') }}
                                        </div>
                                        @if ($feedback->content)
                                            @php
                                                $safeContent = e($feedback->content);
                                                $truncatedContent = Str::limit($safeContent, $truncateLength, '');
                                            @endphp
                                            <div x-data="{ expanded: false }" class="text-gray-800 text-sm leading-relaxed">
                                                <div x-show="!expanded" x-cloak>
                                                    {!! nl2br($truncatedContent) !!}
                                                    @if ($needsTruncation)
                                                        ...
                                                    @endif
                                                </div>
                                                <div x-show="expanded" x-collapse.duration.300ms x-cloak>
                                                    {!! nl2br($safeContent) !!}
                                                </div>
                                                @if ($needsTruncation)
                                                    <div class="mt-2 text-right">
                                                        <flux:link href="javascript:void(0)" class="text-xs" variant="subtle" @click="expanded = !expanded" x-text="expanded ? 'Voir moins' : 'Voir plus'"></flux:link>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <p class="text-gray-500 italic text-sm">Pas de contenu pour ce feedback.</p>
                                        @endif
                                    </flux:card>
                                    @break

                                @case('injury')
                                    @php $injury = $item['data']; @endphp
                                    <flux:card class="border-red-300! bg-red-50! border shadow-lg">
                                        <div class="flex items-center gap-3">
                                            <x-heroicon-o-shield-exclamation class="w-6 h-6 text-red-600"/>
                                            <flux:heading level="3" size="sm" class="font-semibold text-red-800">
                                                Déclaration de blessure
                                            </flux:heading>
                                        </div>
                                        <div class="mt-3 space-y-1 text-sm">
                                            <p><strong class="font-medium">Zone :</strong> {{ $injury->body_part->getLabel() }}</p>
                                            <p><strong class="font-medium">Type :</strong> {{ $injury->type->getLabel() }}</p>
                                            <p><strong class="font-medium">Statut :</strong> <flux:badge color="danger" size="xs">{{ $injury->status->getLabel() }}</flux:badge></p>
                                        </div>
                                        @if($injury->description)
                                            <p class="text-gray-700 mt-2 text-xs italic">"{{ $injury->description }}"</p>
                                        @endif
                                    </flux:card>
                                    @break

                                @case('recovery_protocol')
                                    @php $protocol = $item['data']; @endphp
                                    <flux:card class="border-blue-300! bg-blue-50! border shadow-lg">
                                        <div class="flex items-center gap-3">
                                            <x-heroicon-o-clipboard-document-list class="w-6 h-6 text-blue-600"/>
                                            <flux:heading level="3" size="sm" class="font-semibold text-blue-800">
                                                Protocole de récupération assigné
                                            </flux:heading>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-700">{{ $protocol->title }}</p>
                                        <a href="#" class="text-xs font-bold text-blue-600 hover:underline mt-2 inline-block">Voir le protocole</a>
                                    </flux:card>
                                    @break

                                @case('metric_alert')
                                    @php $alert = $item['data']; @endphp
                                    <flux:card class="border-yellow-400! bg-yellow-50! border shadow-lg">
                                        <div class="flex items-center gap-3">
                                            <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-yellow-600"/>
                                            <flux:heading level="3" size="sm" class="font-semibold text-yellow-800">
                                                {{ $alert['title'] ?? 'Alerte Métrique' }}
                                            </flux:heading>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-700">{{ $alert['message'] ?? 'Une valeur inhabituelle a été détectée.' }}</p>
                                    </flux:card>
                                    @break

                                @case('daily_metrics')
                                    @php $metrics = $item['data']; @endphp
                                    <div class="mb-4 mt-2 flex flex-wrap gap-1">
                                    @foreach ($metrics as $metric)
                                        @php
                                            $metricTypeEnum = $metric->metric_type;
                                        @endphp
                                        @if ($metric->value != 'n/a')
                                            <flux:tooltip content="{{ $metricTypeEnum->getLabel() }}" x-data="{}">
                                                <flux:badge size="sm" color="{{ $metricTypeEnum->getColor() }}">
                                                    <span class="{{ $metricTypeEnum->getIconifyTailwind() }} me-1 size-4"></span>
                                                    {{ $metric->value }}
                                                </flux:badge>
                                            </flux:tooltip>
                                        @endif
                                    @endforeach
                                    </div>
                                    @break

                            @endswitch
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="p-8">
                    <flux:callout icon="calendar-days">
                        <flux:callout.heading>Aucune donnée à afficher</flux:callout.heading>
                        <flux:callout.text>
                            Il semble qu'il n'y ait aucune activité enregistrée pour la période sélectionnée. Essayez de sélectionner une autre période ou d'ajouter de nouvelles données.
                        </flux:callout.text>
                    </flux:callout>
                </div>
            @endforelse
        </div>

        {{-- Liens de pagination --}}
        <div class="px-4 py-6 sm:px-6 border-t border-gray-200">
            {{ $timelinePaginator->appends(request()->query())->links() }}
        </div>
    </div>
</x-layouts.athlete>