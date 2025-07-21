<div class="max-w-4xl mx-auto p-6">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">
                Modifier le feedback médical
            </h1>
            <div class="text-sm text-gray-600">
                <p><strong>Athlète :</strong> {{ $medicalFeedback->injury->athlete->name }}</p>
                <p><strong>Blessure :</strong> {{ $medicalFeedback->injury->type }} - {{ $medicalFeedback->injury->location }}</p>
                <p><strong>Date de la blessure :</strong> {{ $medicalFeedback->injury->declaration_date->format('d.m.Y') }}</p>
                <p><strong>Statut de la blessure :</strong> 
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @if($medicalFeedback->injury->status === \App\Enums\InjuryStatus::DECLARED) bg-red-100 text-red-800
                        @elseif($medicalFeedback->injury->status === \App\Enums\InjuryStatus::IN_REHABILITATION) bg-yellow-100 text-yellow-800
                        @elseif($medicalFeedback->injury->status === \App\Enums\InjuryStatus::RESOLVED) bg-green-100 text-green-800
                        @endif">
                        {{ $medicalFeedback->injury->status->getLabel() }}
                    </span>
                </p>
                @if ($medicalFeedback->reported_by_athlete)
                    <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                        <p class="text-blue-800 text-sm">
                            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            Ce feedback a été initialement créé par l'athlète. Vous pouvez le compléter ou le modifier.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <div class="border-t border-gray-200 pt-6">
            <form wire:submit="save">
                {{ $this->form }}

                <div class="mt-6 flex items-center justify-between">
                    <a href="{{ route('trainers.athlete', ['hash' => $trainer->hash, 'athlete' => $medicalFeedback->injury->athlete_id]) }}" 
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Retour à l'athlète
                    </a>

                    <button type="submit" 
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Mettre à jour le feedback
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-yellow-800">
                    Conseils pour l'édition
                </h3>
                <div class="mt-2 text-sm text-yellow-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li>Complétez les informations manquantes basées sur votre expertise</li>
                        <li>Ajustez les limitations d'entraînement selon votre programme</li>
                        <li>Précisez le plan de traitement si nécessaire</li>
                        <li>Vos modifications seront enregistrées et associées à votre nom</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>