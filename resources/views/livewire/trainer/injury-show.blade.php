<div class="container mx-auto">
    <flux:heading size="xl">Blessure de {{ $injury->athlete->name }}</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Détails de la blessure du {{ $injury->declaration_date->format('d.m.Y') }}.</flux:text>
    <flux:separator class="my-8" variant="subtle" />

    <section class="mb-8 rounded-lg border border-gray-200 bg-gray-50 p-6">
        <h2 class="mb-4 flex items-center text-xl font-bold text-gray-800">
            <svg class="mr-2 h-6 w-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Informations Générales
        </h2>
        <div class="grid grid-cols-1 gap-x-6 gap-y-4 text-gray-700 md:grid-cols-2">
            <div>
                <flux-text class="mb-1 block text-sm">Athlète</flux-text>
                <flux-text class="font-semibold">{{ $injury->athlete->name }}</flux-text>
            </div>
            <div>
                <flux-text class="mb-1 block text-sm">Date de Déclaration</flux-text>
                <flux-text class="font-semibold">{{ $injury->declaration_date->format('d.m.Y') }}</flux-text>
            </div>
            <div>
                <flux-text class="mb-1 block text-sm">Statut</flux-text>
                <flux-text class="font-semibold">
                    <flux:badge :color="$injury->status->getColor()">{{ $injury->status->getLabel() }}</flux:badge>
                </flux-text>
            </div>
            <div>
                <flux-text class="mb-1 block text-sm">Type</flux-text>
                <flux-text class="font-semibold">{{ $injury->injury_type?->getLabel() ?? 'n/a' }}</flux-text>
            </div>
            <div>
                <flux-text class="mb-1 block text-sm">Intensité</flux-text>
                <flux-text class="font-semibold">{{ $injury->pain_intensity ?? 'n/a' }} / 10</flux-text>
            </div>
        </div>
    </section>

    <section class="mb-8">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="flex items-center text-xl font-bold text-gray-800">
                <svg class="mr-2 h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10H5M3 7h12M17 7h-6M17 10h2M21 7h-2M18 21v-8a2 2 0 00-2-2H8a2 2 0 00-2 2v8"></path></svg>
                Feedbacks médicaux
            </h2>
            <flux:button href="{{ route('trainers.injuries.health-events.create', ['hash' => $trainer->hash, 'injury' => $injury->id]) }}"
                variant="primary"
                icon="plus">Ajouter un feedback</flux:button>
        </div>

        @if ($injury->healthEvents->isEmpty())
             <div class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 py-10 text-center">
                <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <p class="mt-4 text-lg text-gray-600">Aucun feedback médical pour cette blessure.</p>
            </div>
        @else
            <div class="space-y-6">
                @foreach ($injury->healthEvents->sortByDesc('feedback_date') as $healthEvent)
                    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-md">
                        <div class="mb-4 flex items-start justify-between">
                            <div>
                                <p class="mb-1 text-base font-bold text-gray-900">
                                    {{ $healthEvent->professional?->name }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    <span class="font-medium">Date:</span> {{ $healthEvent->date->format('d.m.Y') }}
                                </p>
                            </div>
                        </div>

                        @if ($healthEvent->diagnosis)
                            <div class="mb-3">
                                <p class="mb-1 text-sm font-medium text-gray-700">Diagnostic:</p>
                                <div class="rounded border border-gray-200 bg-gray-50 p-3 text-sm">{{ $healthEvent->diagnosis }}</div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <div class="mt-8 flex justify-center border-t border-gray-200 pt-6">
        <flux:button href="{{ route('trainers.injuries.index', ['hash' => $trainer->hash]) }}"
            variant="filled"
            icon="arrow-long-left">Retour à la liste</flux:button>
    </div>
</div>