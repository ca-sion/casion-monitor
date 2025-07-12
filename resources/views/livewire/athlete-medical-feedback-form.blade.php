<div class="max-w-4xl mx-auto p-6">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">
                Ajouter un feedback médical
            </h1>
            <div class="text-sm text-gray-600">
                <p><strong>Blessure :</strong> {{ $injury->type }} - {{ $injury->location }}</p>
                <p><strong>Date de la blessure :</strong> {{ $injury->declaration_date->format('d/m/Y') }}</p>
                <p><strong>Statut :</strong> 
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @if($injury->status === \App\Enums\InjuryStatus::DECLARED) bg-red-100 text-red-800
                        @elseif($injury->status === \App\Enums\InjuryStatus::IN_REHABILITATION) bg-yellow-100 text-yellow-800
                        @elseif($injury->status === \App\Enums\InjuryStatus::RESOLVED) bg-green-100 text-green-800
                        @endif">
                        {{ $injury->status->getLabel() }}
                    </span>
                </p>
            </div>
        </div>

        <div class="border-t border-gray-200 pt-6">
            <form wire:submit="save">
                {{ $this->form }}

                <div class="mt-6 flex items-center justify-between">
                    <a href="{{ route('athletes.injuries.show', ['hash' => $athlete->hash, 'injury' => $injury->id]) }}" 
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Retour
                    </a>

                    <button type="submit" 
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Enregistrer le feedback
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">
                    Information importante
                </h3>
                <div class="mt-2 text-sm text-blue-700">
                    <p>Ce formulaire vous permet de documenter les informations reçues lors de votre consultation médicale. Votre entraîneur pourra ensuite compléter ou modifier ces informations si nécessaire.</p>
                    <ul class="list-disc list-inside mt-2 space-y-1">
                        <li>Remplissez les champs avec les informations communiquées par le professionnel de santé</li>
                        <li>Tous les champs sont optionnels, mais plus vous fournirez d'informations, mieux votre entraîneur pourra adapter votre programme</li>
                        <li>Vous pouvez ajouter plusieurs feedbacks pour la même blessure si vous consultez plusieurs fois</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
