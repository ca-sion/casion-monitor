<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <h2 class="text-2xl font-semibold mb-4">Détails de la Blessure</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-gray-600"><strong>Date de Déclaration:</strong> {{ $injury->declaration_date->format('d/m/Y') }}</p>
                        <p class="text-gray-600"><strong>Statut:</strong> <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $injury->status->getColor() }}">{{ $injury->status->getLabel() }}</span></p>
                        <p class="text-gray-600"><strong>Type de Blessure:</strong> {{ $injury->injury_type?->getLabel() ?? 'N/A' }}</p>
                        <p class="text-gray-600"><strong>Intensité de la Douleur:</strong> {{ $injury->pain_intensity ?? 'N/A' }}</p>
                        <p class="text-gray-600"><strong>Localisation de la Douleur:</strong> {{ $injury->pain_location ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-600"><strong>Liée à une Session:</strong> {{ $injury->session_related ? 'Oui' : 'Non' }}</p>
                        @if ($injury->session_related)
                            <p class="text-gray-600"><strong>Date de la Session:</strong> {{ $injury->session_date?->format('d/m/Y') ?? 'N/A' }}</p>
                        @endif
                        <p class="text-gray-600"><strong>Apparition Immédiate:</strong> {{ $injury->immediate_onset ? 'Oui' : 'Non' }}</p>
                    </div>
                </div>

                <div class="mt-4">
                    <p class="text-gray-600"><strong>Circonstances d'Apparition:</strong></p>
                    <p class="bg-gray-100 p-3 rounded-md">{{ $injury->onset_circumstances ?? 'N/A' }}</p>
                </div>

                <div class="mt-4">
                    <p class="text-gray-600"><strong>Impact sur l'Entraînement:</strong></p>
                    <p class="bg-gray-100 p-3 rounded-md">{{ $injury->impact_on_training ?? 'N/A' }}</p>
                </div>

                <div class="mt-4">
                    <p class="text-gray-600"><strong>Description Détaillée:</strong></p>
                    <p class="bg-gray-100 p-3 rounded-md">{{ $injury->description ?? 'N/A' }}</p>
                </div>

                <div class="mt-4">
                    <p class="text-gray-600"><strong>Ressenti de l'Athlète sur le Diagnostic:</strong></p>
                    <p class="bg-gray-100 p-3 rounded-md">{{ $injury->athlete_diagnosis_feeling ?? 'N/A' }}</p>
                </div>

                <div class="mt-6">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-semibold">Feedbacks Médicaux</h3>
                        <a href="{{ route('athletes.injuries.feedback.create', ['hash' => $athlete->hash, 'injury' => $injury->id]) }}"
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Ajouter feedback médical
                        </a>
                    </div>
                    
                    @if ($injury->medicalFeedbacks->isEmpty())
                        <div class="text-center py-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="mt-2 text-sm text-gray-600">Aucun feedback médical pour cette blessure.</p>
                            <p class="text-xs text-gray-500 mt-1">Ajoutez un feedback après votre consultation médicale.</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($injury->medicalFeedbacks->sortByDesc('feedback_date') as $feedback)
                                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ $feedback->professional_type->getLabel() }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ $feedback->feedback_date->format('d/m/Y') }}
                                                @if ($feedback->reported_by_athlete)
                                                    • Rapporté par l'athlète
                                                @endif
                                                @if ($feedback->trainer)
                                                    • Complété par {{ $feedback->trainer->name }}
                                                @endif
                                            </p>
                                        </div>
                                        @if ($feedback->next_appointment_date)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                Prochain RDV: {{ $feedback->next_appointment_date->format('d/m/Y') }}
                                            </span>
                                        @endif
                                    </div>
                                    
                                    @if ($feedback->diagnosis)
                                        <div class="mb-3">
                                            <p class="text-xs font-medium text-gray-700 mb-1">Diagnostic:</p>
                                            <p class="text-sm text-gray-800 bg-gray-50 p-2 rounded">{{ $feedback->diagnosis }}</p>
                                        </div>
                                    @endif
                                    
                                    @if ($feedback->treatment_plan)
                                        <div class="mb-3">
                                            <p class="text-xs font-medium text-gray-700 mb-1">Plan de traitement:</p>
                                            <p class="text-sm text-gray-800 bg-gray-50 p-2 rounded">{{ $feedback->treatment_plan }}</p>
                                        </div>
                                    @endif
                                    
                                    @if ($feedback->training_limitations)
                                        <div class="mb-3">
                                            <p class="text-xs font-medium text-gray-700 mb-1">Limitations d'entraînement:</p>
                                            <p class="text-sm text-gray-800 bg-yellow-50 p-2 rounded border-l-4 border-yellow-400">{{ $feedback->training_limitations }}</p>
                                        </div>
                                    @endif
                                    
                                    @if ($feedback->rehab_progress)
                                        <div class="mb-3">
                                            <p class="text-xs font-medium text-gray-700 mb-1">Progrès de rééducation:</p>
                                            <p class="text-sm text-gray-800 bg-gray-50 p-2 rounded">{{ $feedback->rehab_progress }}</p>
                                        </div>
                                    @endif
                                    
                                    @if ($feedback->notes)
                                        <div class="mb-3">
                                            <p class="text-xs font-medium text-gray-700 mb-1">Notes complémentaires:</p>
                                            <p class="text-sm text-gray-800 bg-gray-50 p-2 rounded">{{ $feedback->notes }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-6">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xl font-semibold">Protocoles de Récupération</h3>
                        <a href="{{ route('athletes.injuries.recovery-protocols.create', ['hash' => $athlete->hash, 'injury' => $injury->id]) }}"
                           class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Ajouter protocole
                        </a>
                    </div>
                    
                    @if ($injury->recoveryProtocols->isEmpty())
                        <div class="text-center py-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="mt-2 text-sm text-gray-600">Aucun protocole de récupération pour cette blessure.</p>
                            <p class="text-xs text-gray-500 mt-1">Ajoutez un protocole pour suivre votre récupération.</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($injury->recoveryProtocols->sortByDesc('date') as $protocol)
                                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ $protocol->recovery_type->getLabel() }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ $protocol->date->format('d/m/Y') }}
                                                @if ($protocol->duration_minutes)
                                                    • {{ $protocol->duration_minutes }} minutes
                                                @endif
                                            </p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            @if ($protocol->effect_on_pain_intensity)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    Douleur: {{ $protocol->effect_on_pain_intensity }}/10
                                                </span>
                                            @endif
                                            @if ($protocol->effectiveness_rating)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Efficacité: {{ $protocol->effectiveness_rating }}/5
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    @if ($protocol->notes)
                                        <div class="mb-3">
                                            <p class="text-xs font-medium text-gray-700 mb-1">Notes:</p>
                                            <p class="text-sm text-gray-800 bg-gray-50 p-2 rounded">{{ $protocol->notes }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-6">
                    <a href="{{ route('athletes.injuries.index', ['hash' => $athlete->hash]) }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Retour à la liste des blessures
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
