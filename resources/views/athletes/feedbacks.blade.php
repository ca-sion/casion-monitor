<x-layouts.athlete :title="$athlete->name">
    <div class="container mx-auto">
        <div class="bg-white shadow-xl rounded-lg overflow-hidden">
            <div class="px-4 py-6 sm:px-6 border-b border-gray-200 bg-gradient-to-r from-gray-100 to-white">
                <flux:heading size="xl" level="1" class="font-extrabold text-gray-900 mb-1">
                    Journal de bord
                </flux:heading>
                <flux:text class="text-gray-600 text-md">
                    Retrouvez ici tous les feedbacks. Utilisez les filtres ci-dessous pour affiner votre recherche.
                </flux:text>
            </div>

            {{-- Section des filtres --}}
            <div class="px-4 py-6 sm:px-6 bg-gray-50 border-b border-gray-200">
                <form action="{{ route('athletes.feedbacks', ['hash' => $athlete->hash]) }}" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label for="filter_type" class="block text-sm font-medium text-gray-700">Filtrer par type</label>
                        <select id="filter_type" name="filter_type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="">Tous les types</option>
                            @foreach ($feedbackTypes as $type)
                                <option value="{{ $type->value }}" @selected($currentFilterType === $type->value)>
                                    {{ $type->getLabel() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="filter_category" class="block text-sm font-medium text-gray-700">Filtrer par catégorie</label>
                        <select id="filter_category" name="filter_category" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="">Toutes les catégories</option>
                            <option value="session" @selected($currentFilterCategory === 'session')>Séance</option>
                            <option value="competition" @selected($currentFilterCategory === 'competition')>Compétition</option>
                        </select>
                    </div>

                    <div>
                        <label for="period" class="block text-sm font-medium text-gray-700">Période</label>
                        <select id="period" name="period" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            @foreach ($periodOptions as $key => $label)
                                <option value="{{ $key }}" @selected($currentPeriod === $key)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-span-full md:col-span-3 text-right">
                        <flux:button type="submit" variant="primary" class="w-full md:w-auto">
                            Appliquer les filtres
                        </flux:button>
                    </div>
                </form>
            </div>
            {{-- Fin de la section des filtres --}}


            @forelse ($groupedFeedbacks as $date => $feedbacksGroup)
                <div class="px-4 py-8 sm:p-8 border-b border-gray-100 last:border-b-0">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-500 text-white text-lg font-bold shadow-lg mr-4">
                            {{ \Carbon\Carbon::parse($date)->isoFormat('dd') }}
                        </div>
                        <flux:heading size="xl" level="2" class="font-bold text-gray-800">
                            {{ \Carbon\Carbon::parse($date)->isoFormat('ll') }}
                        </flux:heading>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($feedbacksGroup as $feedback)
                            @php
                                $isTrainerFeedback = ($feedback->author_type === 'trainer');
                                $isCompetitionFeedback = in_array($feedback->type->value, [
                                    \App\Enums\FeedbackType::PRE_COMPETITION_GOALS->value,
                                    \App\Enums\FeedbackType::POST_COMPETITION_FEEDBACK->value,
                                    \App\Enums\FeedbackType::POST_COMPETITION_SENSATION->value,
                                ]);

                                // Couleurs de base (athlète/entraîneur)
                                $cardBg = $isTrainerFeedback ? 'bg-slate-50!' : 'bg-yellow-50!';
                                $cardBorder = $isTrainerFeedback ? 'border-slate-300!' : 'border-yellow-300!';
                                $badgeColor = $isTrainerFeedback ? 'slate' : 'yellow';
                                $authorText = $isTrainerFeedback ? ($feedback->trainer?->first_name) : 'Moi';

                                // Surcharge pour les feedbacks de compétition
                                if ($isCompetitionFeedback) {
                                    $cardBg = 'bg-purple-50!';
                                    $cardBorder = 'border-purple-400!';
                                    $badgeColor = 'purple';
                                }

                                // Longueur maximale du contenu affiché avant le "voir plus"
                                $truncateLength = 200;
                                $needsTruncation = strlen($feedback->content) > $truncateLength;
                            @endphp

                            <flux:card class="shadow-lg hover:shadow-xl transition-shadow duration-300 {{ $cardBg }} {{ $cardBorder }} border-2">
                                <div class="flex items-center justify-between">
                                    <flux:badge color="{{ $badgeColor }}" size="sm">
                                        {{ $feedback->type->getLabel() }}
                                    </flux:badge>
                                    <flux:text class="text-xs">
                                        {{ $authorText }}
                                    </flux:text>
                                </div>
                                <div class="text-[8px] text-gray-500 mb-3 mt-1">
                                    {{ \Carbon\Carbon::parse($feedback->created_at)->format('d.m.Y à H:i') }}
                                </div>
                                {{-- Section du contenu avec "Voir plus" / Alpine.js Collapse --}}
                                @if ($feedback->content)
                                    {{-- x-data est déplacé sur le conteneur principal du contenu --}}
                                    <div x-data="{ expanded: false }" class="text-gray-800 text-sm leading-relaxed">
                                        {{-- Contenu tronqué --}}
                                        <div x-show="!expanded" x-cloak>
                                            {!! nl2br(e(Str::limit($feedback->content, $truncateLength, ''))) !!}
                                            @if ($needsTruncation)
                                                ...
                                            @endif
                                        </div>

                                        {{-- Contenu complet avec x-collapse --}}
                                        <div x-show="expanded" x-collapse.duration.300ms x-cloak>
                                            {!! nl2br(e($feedback->content)) !!}
                                        </div>

                                        @if ($needsTruncation)
                                            <div class="mt-2 text-right">
                                                <flux:button
                                                    size="xs"
                                                    variant="subtle"
                                                    @click="expanded = !expanded"
                                                    x-text="expanded ? 'Voir moins' : 'Voir plus'"
                                                >
                                                </flux:button>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <div class="text-gray-800 text-sm leading-relaxed">
                                        <p class="text-gray-500 italic">Pas de contenu pour ce feedback.</p>
                                    </div>
                                @endif
                                {{-- Fin de la section du contenu --}}
                            </flux:card>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="p-8">
                    <flux:callout icon="chat-bubble-left-right">
                        <flux:callout.heading>Aucun feedback trouvé</flux:callout.heading>

                        <flux:callout.text>
                            Il semble qu'aucun feedback ne corresponde à vos critères de recherche pour cette période.
                        </flux:callout.text>
                    </flux:callout>
                </div>
            @endforelse

            {{-- Liens de pagination --}}
            <div class="px-4 py-6 sm:px-6 border-t border-gray-200">
                {{ $feedbacksPaginator->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</x-layouts.athlete>