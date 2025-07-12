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
                    <a href="{{ route('athletes.injuries.index', ['hash' => $athlete->hash]) }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Retour à la liste des blessures
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
