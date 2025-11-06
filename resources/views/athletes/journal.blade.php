<x-layouts.athlete :title="$athlete->name">
    <div class="container mx-auto">
        {{-- En-tête --}}
        <div class="py-6 sm:px-6">
            <flux:heading class="mb-1 font-extrabold text-gray-900"
                size="xl"
                level="1">
                Journal d'entraînement
            </flux:heading>
            <flux:text class="text-md text-gray-600">
                Ton journal pour suivre tes entraînements, tes feedbacks et ton état.
            </flux:text>
        </div>

        {{-- Section des filtres --}}
        <div class="border-b border-t border-gray-200 bg-gray-50 px-4 py-4 sm:px-6">
            <form class="flex flex-col items-center gap-4 sm:flex-row"
                action="{{ route('athletes.journal', ['hash' => $athlete->hash]) }}"
                method="GET">
                <div class="w-full sm:w-auto">
                    <label class="sr-only block text-sm font-medium text-gray-700" for="period">Période</label>
                    <flux:select class="w-full"
                        id="period"
                        name="period"
                        placeholder="Sélectionner une période">
                        @foreach ($periodOptions as $key => $label)
                            <option value="{{ $key }}" @selected($currentPeriod === $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </flux:select>
                </div>
                <div class="w-full sm:w-auto">
                    <flux:button class="w-full justify-center"
                        type="submit"
                        variant="primary"
                        icon="magnifying-glass">
                        Appliquer
                    </flux:button>
                </div>
            </form>
        </div>

        {{-- Timeline --}}
        <div class="sm:p-6">
            @forelse ($groupedItems as $date => $items)
                <div class="group relative py-6 pl-8 sm:pl-10">
                    {{-- Date et icône sur la ligne de temps --}}
                    <div class="mb-4 flex items-center">
                        <div class="absolute left-0 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-gray-600 ring-4 ring-white sm:h-10 sm:w-10 dark:ring-gray-900">
                            <span class="text-sm font-bold text-white">{{ \Carbon\Carbon::parse($date)->isoFormat('DD') }}</span>
                        </div>
                        <flux:heading class="ml-4 font-bold text-gray-800"
                            level="2"
                            size="lg">
                            {{ \Carbon\Carbon::parse($date)->isoFormat('ddd DD MMMM') }}
                        </flux:heading>
                        <flux:button class="ms-2 inline"
                            href="{{ route('athletes.metrics.daily.form', ['hash' => $athlete->hash, 'd' => $date]) }}"
                            variant="filled"
                            size="xs"
                            icon="pencil-square">
                        </flux:button>
                        <flux:button class="ms-2 inline"
                            href="{{ route('athletes.feedbacks.create', ['hash' => $athlete->hash, 'd' => $date]) }}"
                            variant="filled"
                            size="xs"
                            icon="chat-bubble-left-ellipsis">
                        </flux:button>
                        <flux:button class="ms-2 inline"
                            href="{{ route('athletes.health-events.create', ['hash' => $athlete->hash, 'date' => $date]) }}"
                            variant="filled"
                            size="xs"
                            icon="stethoscope">
                        </flux:button>
                    </div>

                    {{-- Ligne verticale de la timeline --}}
                    <div class="absolute left-4 top-0 h-full w-0.5 bg-gray-200 sm:left-5"></div>

                    {{-- Cartes d'événements --}}
                    <div class="space-y-6">
                        @foreach ($items as $item)
                            @switch($item['type'])
                                @case('feedback')
                                    @php
                                        $feedback = $item['data'];
                                        $authorText = $feedback->author_type === 'trainer' ? $feedback->trainer?->first_name ?? 'Entraîneur' : 'Moi';
                                        $truncateLength = 200;
                                        $needsTruncation = strlen($feedback->content) > $truncateLength;
                                    @endphp
                                    <flux:callout class="p-0!"
                                        :icon="$feedback->author_type === 'trainer' ? 'user-circle' : 'document-text'"
                                        :color="$feedback->author_type === 'trainer' ? 'teal' : 'stone'">
                                        <flux:callout.heading>
                                            {{ $feedback->type->getLabel() }} ({{ $authorText }})
                                        </flux:callout.heading>
                                        <flux:callout.text>
                                            @if ($feedback->content)
                                                @php
                                                    $safeContent = e($feedback->content);
                                                    $truncatedContent = Str::limit($safeContent, $truncateLength, '');
                                                @endphp
                                                <div class="text-sm leading-relaxed text-gray-800" x-data="{ expanded: false }">
                                                    <div x-show="!expanded" x-cloak>
                                                        {!! nl2br($truncatedContent) !!}
                                                        @if ($needsTruncation)
                                                            ...
                                                        @endif
                                                    </div>
                                                    <div x-show="expanded"
                                                        x-collapse.duration.300ms
                                                        x-cloak>
                                                        {!! nl2br($safeContent) !!}
                                                    </div>
                                                    @if ($needsTruncation)
                                                        <div class="mt-2 text-right">
                                                            <flux:link class="text-xs"
                                                                href="javascript:void(0)"
                                                                variant="subtle"
                                                                @click="expanded = !expanded"
                                                                x-text="expanded ? 'Voir moins' : 'Voir plus'"></flux:link>
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                <p class="text-sm italic text-gray-500">Pas de contenu pour ce feedback.</p>
                                            @endif
                                        </flux:callout.text>
                                    </flux:callout>
                                @break

                                @case('injury')
                                    @php $injury = $item['data']; @endphp

                                    <flux:card class="border-red-300! bg-red-50! border shadow-lg">
                                        <div class="flex items-center gap-3">
                                            <x-heroicon-o-shield-exclamation class="h-6 w-6 text-red-600" />
                                            <flux:heading class="font-semibold text-red-800"
                                                level="3"
                                                size="sm">
                                                Déclaration de blessure
                                            </flux:heading>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-800">{{ $injury->injury_type?->getLabel() }} : {{ $injury->pain_location?->getLabel() }}</p>
                                        @if ($injury->description)
                                            <p class="mt-2 text-xs italic text-gray-700">{{ $injury->description }}</p>
                                        @endif
                                        <flux:button class="mt-3"
                                            size="sm"
                                            :href="route('athletes.injuries.show', ['hash' => $athlete->hash, 'injury' => $injury])">Voir</flux:button>
                                    </flux:card>
                                @break

                                @case('recovery_protocol')
                                    @php $protocol = $item['data']; @endphp
                                    <flux:callout class="p-0!"
                                        icon="stethoscope"
                                        color="purple"
                                        inline>
                                        <flux:callout.heading>
                                            {{ $protocol->recovery_type->getLabel() }}
                                        </flux:callout.heading>
                                        <flux:callout.text>
                                            {{ $protocol->notes }}
                                            @if ($protocol->duration_minutes)
                                                • {{ $protocol->duration_minutes }} minutes
                                            @endif
                                            @if ($protocol->relatedInjury)
                                                • Lié à la blessure: {{ $protocol->relatedInjury->type }}
                                            @endif
                                        </flux:callout.text>
                                        <x-slot name="actions">
                                            <flux:button size="sm" :href="route('athletes.health-events.edit', ['hash' => $athlete->hash, 'healthEvent' => $protocol])">Voir</flux:button>
                                            @if ($protocol->effectiveness_rating)
                                                <flux:badge class="whitespace-normal!"
                                                    size="sm"
                                                    inset="top bottom"
                                                    color="green">
                                                    {{ $protocol->effectiveness_rating }}/5
                                                </flux:badge>
                                            @endif
                                            @if ($protocol->effect_on_pain_intensity)
                                                <flux:badge class="whitespace-normal!"
                                                    size="sm"
                                                    inset="top bottom"
                                                    color="blue">
                                                    {{ $protocol->effect_on_pain_intensity }}/10
                                                </flux:badge>
                                            @endif
                                        </x-slot>
                                    </flux:callout>
                                @break

                                @case('metric_alert')
                                    @php $alert = $item['data']; @endphp
                                    <flux:card class="border-yellow-400! bg-yellow-50! border shadow-lg">
                                        <div class="flex items-center gap-3">
                                            <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-yellow-600" />
                                            <flux:heading class="font-semibold text-yellow-800"
                                                level="3"
                                                size="sm">
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
            <div class="border-t border-gray-200 px-4 py-6 sm:px-6">
                {{ $timelinePaginator->appends(request()->query())->links() }}
            </div>
        </div>
    </x-layouts.athlete>
