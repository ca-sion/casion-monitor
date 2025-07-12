<div class="">
    <div class="container mx-auto">
        <div class="bg-white shadow-xl rounded-lg">
            <div class="px-4 py-6 sm:px-6 border-b border-gray-200 bg-gradient-to-r from-gray-100 to-white">
                <flux:heading size="xl" level="1" class="font-extrabold text-gray-900 mb-1">
                    Détail de la blessure/douleur
                </flux:heading>
            </div>

            <div class="mx-auto p-8">
                <section class="mb-8 p-6 bg-gray-50 rounded-lg border border-gray-200">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Informations Générales
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6 text-gray-700">
                    <div>
                        <flux-text class="block text-sm mb-1">Date de Déclaration</flux-text>
                        <flux-text class="font-semibold">{{ $injury->declaration_date->format('d.m.Y') }}</flux-text>
                    </div>
                    <div>
                        <flux-text class="block text-sm mb-1">Statut</flux-text>
                        <flux-text class="font-semibold">
                            <flux:badge :color="$injury->status->getColor()">{{ $injury->status->getLabel() }}</flux:badge>
                        </flux-text>
                    </div>
                    <div>
                        <flux-text class="block text-sm mb-1">Type</flux-text>
                        <flux-text class="font-semibold">{{ $injury->injury_type?->getLabel() ?? 'N/A' }}</flux-text>
                    </div>
                    <div>
                        <flux-text class="block text-sm mb-1">Intensité</flux-text>
                        <flux-text class="font-semibold">{{ $injury->pain_intensity ?? 'N/A' }} / 10</flux-text>
                    </div>
                    <div>
                        <flux-text class="block text-sm mb-1">Liée à une session</flux-text>
                        <flux-text class="font-semibold">{{ $injury->session_related ? 'Oui' : 'Non' }}</flux-text>
                        @if ($injury->session_related)
                            <flux-text class="block text-sm mt-1">{{ $injury->session_date?->format('d.m.Y') ?? 'N/A' }}</flux-text>
                        @endif
                    </div>
                    <div>
                        <flux-text class="block text-sm mb-1">Apparition immédiate</flux-text>
                        <flux-text class="font-semibold">{{ $injury->immediate_onset ? 'Oui' : 'Non' }}</flux-text>
                    </div>
                </div>
            </section>

            <section class="mb-8 space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h10M7 16h10M9 17l-5 4v-4H4a2 2 0 01-2-2V7a2 2 0 012-2h16a2 2 0 012 2v10a2 2 0 01-2 2h-5l-5 4z"></path></svg>
                        Circonstances d'Apparition
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-gray-800 leading-relaxed">
                        {{ $injury->onset_circumstances ?? 'Non spécifié.' }}
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Impact sur l'Entraînement
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-gray-800 leading-relaxed">
                        {{ $injury->impact_on_training ?? 'Non spécifié.' }}
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                        Description Détaillée
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-gray-800 leading-relaxed">
                        {{ $injury->description ?? 'Aucune description fournie.' }}
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H2v-2a3 3 0 015.356-1.857M9 20v-2a3 3 0 00-3-3H4a3 3 0 00-3 3v2m14-5a3 3 0 11-6 0 3 3 0 016 0zm-7 0a1 1 0 10-2 0 1 1 0 002 0zm-9-5a3 3 0 11-6 0 3 3 0 016 0zM7 8a1 1 0 10-2 0 1 1 0 002 0z"></path></svg>
                        Ressenti de l'Athlète sur le Diagnostic
                    </h3>
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-gray-800 leading-relaxed">
                        {{ $injury->athlete_diagnosis_feeling ?? 'Non spécifié.' }}
                    </div>
                </div>
            </section>

            <section class="mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10H5M3 7h12M17 7h-6M17 10h2M21 7h-2M18 21v-8a2 2 0 00-2-2H8a2 2 0 00-2 2v8"></path></svg>
                        Feedbacks Médicaux
                    </h2>
                    <flux:button variant="primary" icon="plus" href="{{ route('athletes.injuries.feedback.create', ['hash' => $athlete->hash, 'injury' => $injury->id]) }}">Ajouter un feedback</flux:button>
                </div>
                
                @if ($injury->medicalFeedbacks->isEmpty())
                    <div class="text-center py-10 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                        <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <p class="mt-4 text-lg text-gray-600">Aucun feedback médical pour cette blessure.</p>
                        <p class="text-sm text-gray-500 mt-2">Ajoutez un feedback après votre consultation médicale pour garder un suivi détaillé.</p>
                    </div>
                @else
                    <div class="space-y-6">
                        @foreach ($injury->medicalFeedbacks->sortByDesc('feedback_date') as $feedback)
                            <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 transition-all duration-300 hover:shadow-lg">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <p class="text-base font-bold text-gray-900 mb-1">
                                            {{ $feedback->professional_type->getLabel() }}
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            <span class="font-medium">Date:</span> {{ $feedback->feedback_date->format('d/m/Y') }}
                                            @if ($feedback->reported_by_athlete)
                                                <span class="mx-2">•</span> Rapporté par l'athlète
                                            @endif
                                            @if ($feedback->trainer)
                                                <span class="mx-2">•</span> Complété par {{ $feedback->trainer->name }}
                                            @endif
                                        </p>
                                    </div>
                                    @if ($feedback->next_appointment_date)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                            Prochain RDV: {{ $feedback->next_appointment_date->format('d/m/Y') }}
                                        </span>
                                    @endif
                                </div>
                                
                                @if ($feedback->diagnosis)
                                    <div class="mb-3">
                                        <p class="text-sm font-medium text-gray-700 mb-1">Diagnostic:</p>
                                        <div class="bg-gray-50 p-3 rounded text-gray-800 text-sm leading-relaxed border border-gray-200">{{ $feedback->diagnosis }}</div>
                                    </div>
                                @endif
                                
                                @if ($feedback->treatment_plan)
                                    <div class="mb-3">
                                        <p class="text-sm font-medium text-gray-700 mb-1">Plan de traitement:</p>
                                        <div class="bg-gray-50 p-3 rounded text-gray-800 text-sm leading-relaxed border border-gray-200">{{ $feedback->treatment_plan }}</div>
                                    </div>
                                @endif
                                
                                @if ($feedback->training_limitations)
                                    <div class="mb-3">
                                        <p class="text-sm font-medium text-gray-700 mb-1">Limitations d'entraînement:</p>
                                        <div class="bg-yellow-50 p-3 rounded text-yellow-900 text-sm leading-relaxed border-l-4 border-yellow-400 font-medium">{{ $feedback->training_limitations }}</div>
                                    </div>
                                @endif
                                
                                @if ($feedback->rehab_progress)
                                    <div class="mb-3">
                                        <p class="text-sm font-medium text-gray-700 mb-1">Progrès de rééducation:</p>
                                        <div class="bg-gray-50 p-3 rounded text-gray-800 text-sm leading-relaxed border border-gray-200">{{ $feedback->rehab_progress }}</div>
                                    </div>
                                @endif
                                
                                @if ($feedback->notes)
                                    <div>
                                        <p class="text-sm font-medium text-gray-700 mb-1">Notes complémentaires:</p>
                                        <div class="bg-gray-50 p-3 rounded text-gray-800 text-sm leading-relaxed border border-gray-200">{{ $feedback->notes }}</div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="mb-8">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                        Protocoles de Récupération
                    </h2>
                    <flux:button variant="primary" icon="plus" href="{{ route('athletes.injuries.recovery-protocols.create', ['hash' => $athlete->hash, 'injury' => $injury->id]) }}">Ajouter un protocole</flux:button>
                </div>
                
                @if ($injury->recoveryProtocols->isEmpty())
                    <div class="text-center py-10 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                        <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <p class="mt-4 text-lg text-gray-600">Aucun protocole de récupération pour cette blessure.</p>
                        <p class="text-sm text-gray-500 mt-2">Ajoutez un protocole pour suivre l'évolution de la récupération.</p>
                    </div>
                @else
                    <div class="space-y-6">
                        @foreach ($injury->recoveryProtocols->sortByDesc('date') as $protocol)
                            <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 transition-all duration-300 hover:shadow-lg">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <p class="text-base font-bold text-gray-900 mb-1">
                                            {{ $protocol->recovery_type->getLabel() }}
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            <span class="font-medium">Date:</span> {{ $protocol->date->format('d/m/Y') }}
                                            @if ($protocol->duration_minutes)
                                                <span class="mx-2">•</span> {{ $protocol->duration_minutes }} minutes
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        @if ($protocol->effect_on_pain_intensity)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                                Douleur: {{ $protocol->effect_on_pain_intensity }}/10
                                            </span>
                                        @endif
                                        @if ($protocol->effectiveness_rating)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                                Efficacité: {{ $protocol->effectiveness_rating }}/5
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                
                                @if ($protocol->notes)
                                    <div>
                                        <p class="text-sm font-medium text-gray-700 mb-1">Notes:</p>
                                        <div class="bg-gray-50 p-3 rounded text-gray-800 text-sm leading-relaxed border border-gray-200">{{ $protocol->notes }}</div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="mt-8 pt-6 border-t border-gray-200 flex justify-center">
                <flux:button variant="filled" icon="arrow-long-left" href="{{ route('athletes.injuries.index', ['hash' => $athlete->hash]) }}" >Retour à la liste</flux:button>
            </div>
            </div>
        </div>
    </div>
</div>
