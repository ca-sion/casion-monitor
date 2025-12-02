<x-layouts.trainer :title="$trainer->name"> {{-- Assurez-vous d'avoir un layout 'trainer' --}}
    <div class="container mx-auto">
        <div class="bg-white shadow-xl rounded-lg overflow-hidden">
            <div class="px-4 py-6 sm:px-6 border-b border-gray-200 bg-gradient-to-r from-gray-100 to-white">
                <flux:heading size="xl" level="1" class="font-extrabold text-gray-900 mb-1">
                    Journal de bord des athlètes
                </flux:heading>
                <flux:text class="text-gray-600 text-md">
                    Retrouvez ici tous les feedbacks de vos athlètes. Utilisez les filtres ci-dessous pour affiner votre recherche.
                </flux:text>
            </div>

            {{-- Section des filtres --}}
            <div class="px-4 py-6 sm:px-6 bg-gray-50 border-b border-gray-200">
                <form action="{{ route('trainers.feedbacks', ['hash' => $trainer->hash]) }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 items-end"> {{-- md:grid-cols-5 pour ajouter un filtre athlète --}}
                    {{-- Filtre par athlète --}}
                    <div>
                        <label for="athlete_id" class="block text-sm font-medium text-gray-700 sr-only">Filtrer par athlète</label>
                        <flux:select id="athlete_id" name="athlete_id" placeholder="Filtrer par athlète" class="w-full">
                            <option value="">Tous les athlètes</option>
                            @foreach ($trainerAthletes as $athleteOption)
                                <option value="{{ $athleteOption->id }}" @selected($currentFilterAthleteId == $athleteOption->id)>
                                    {{ $athleteOption->first_name }} {{ $athleteOption->last_name }}
                                </option>
                            @endforeach
                        </flux:select>
                    </div>

                    {{-- Filtre par type --}}
                    <div>
                        <label for="filter_type" class="block text-sm font-medium text-gray-700 sr-only">Filtrer par type</label>
                        <flux:select id="filter_type" name="filter_type" placeholder="Filtrer par type" class="w-full">
                            <option value="">Tous les types</option>
                            @foreach ($feedbackTypes as $type)
                                <option value="{{ $type->value }}" @selected($currentFilterType === $type->value)>
                                    {{ $type->getLabel() }}
                                </option>
                            @endforeach
                        </flux:select>
                    </div>

                    {{-- Filtre par catégorie --}}
                    <div>
                        <label for="filter_category" class="block text-sm font-medium text-gray-700 sr-only">Filtrer par catégorie</label>
                        <flux:select id="filter_category" name="filter_category" placeholder="Filtrer par catégorie" class="w-full">
                            <option value="">Toutes les catégories</option>
                            <option value="session" @selected($currentFilterCategory === 'session')>Séance</option>
                            <option value="competition" @selected($currentFilterCategory === 'competition')>Compétition</option>
                        </flux:select>
                    </div>

                    {{-- Sélecteur de période --}}
                    <div>
                        <label for="period" class="block text-sm font-medium text-gray-700 sr-only">Période</label>
                        <flux:select id="period" name="period" placeholder="Sélectionner une période" class="w-full">
                            @foreach ($periodOptions as $key => $label)
                                <option value="{{ $key }}" @selected($currentPeriod === $key)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </flux:select>
                    </div>

                    {{-- Bouton Appliquer --}}
                    <div class="text-right">
                        <flux:button type="submit" variant="primary" icon="magnifying-glass" class="w-full">
                            Appliquer
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
                                
                                if ($isTrainerFeedback) {
                                    $authorText = $feedback->trainer ? $feedback->trainer->first_name : 'Entraîneur Inconnu';
                                } else {
                                    $authorText = $feedback->athlete ? $feedback->athlete->first_name . ' ' . $feedback->athlete->last_name : 'Athlète Inconnu';
                                }

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

                                {{-- Nom de l'athlète et lien vers son profil --}}
                                <div class="mb-2">
                                    <flux:text class="text-sm font-semibold text-gray-700">
                                        Pour : <a href="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete_id' => $feedback->athlete->id]) }}" class="text-indigo-600 hover:text-indigo-800 underline">
                                            {{ $feedback->athlete->first_name }} {{ $feedback->athlete->last_name }}
                                        </a>
                                    </flux:text>
                                </div>


                                {{-- Section du contenu avec "Voir plus" / Alpine.js Collapse --}}
                                @if ($feedback->content)
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

                                {{-- Lien d'édition du feedback --}}
                                <div class="mt-4 text-right">
                                    <a href="{{ route('trainers.feedbacks.form', [
                                        'hash' => $trainer->hash,
                                        'athlete' => $feedback->athlete->id,
                                        'd' => \Carbon\Carbon::parse($feedback->date)->format('Y-m-d')
                                    ]) }}"
                                    class="text-sm text-blue-600 hover:text-blue-800 underline flex items-center justify-end">
                                        <flux:icon name="pencil" class="w-4 h-4 mr-1" /> Modifier le feedback
                                    </a>
                                </div>
                            </flux:card>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="p-8">
                    <flux:callout icon="chat-bubble-left-right">
                        <flux:callout.heading>Aucun feedback trouvé</flux:callout.heading>

                        <flux:callout.text>
                            Il semble qu'aucun feedback ne corresponde à vos critères de recherche pour cette période ou qu'aucun de vos athlètes n'ait laissé de feedback.
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
</x-layouts.trainer>