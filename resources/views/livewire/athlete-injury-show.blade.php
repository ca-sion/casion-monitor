<div class="container mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">
                {{ $injury->injury_type?->getPrefixForLocation() }} - {{ $injury->pain_location?->getLabel() }}
            </flux:heading>
            <flux:text class="mt-2 text-base">
                Déclarée le {{ $injury->declaration_date->format('d.m.Y') }}
            </flux:text>
        </div>
        <flux:button
            href="{{ route('athletes.injuries.index', ['hash' => $athlete->hash]) }}"
            variant="filled"
            icon="arrow-long-left"
        >
            Retour à la liste
        </flux:button>
    </div>

    <flux:separator class="my-8" variant="subtle"></flux:separator>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <div class="space-y-8 lg:col-span-2">
            <flux:card class="p-0">
                <div class="border-b border-gray-200 p-4">
                    <div class="flex items-center">
                        <flux:icon name="information-circle" class="mr-2 h-6 w-6 text-primary-500"></flux:icon>
                        <h2 class="text-xl font-bold">Informations Générales</h2>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 gap-x-6 gap-y-6 text-gray-700 md:grid-cols-2">
                        <div>
                            <flux:text class="mb-1 block text-sm">Date de Déclaration</flux:text>
                            <flux:text class="font-semibold">{{ $injury->declaration_date->format('d.m.Y') }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="mb-1 block text-sm">Statut</flux:text>
                            <flux:text class="font-semibold">
                                <flux:badge :color="$injury->status->getColor()" size="sm">{{ $injury->status->getLabel() }}</flux:badge>
                            </flux:text>
                        </div>
                        <div>
                            <flux:text class="mb-1 block text-sm">Type</flux:text>
                            <flux:text class="font-semibold">{{ $injury->injury_type?->getLabel() ?? 'n/a' }}</flux:text>
                        </div>
                        <div>
                            <flux:text class="mb-1 block text-sm">Intensité</flux:text>
                            <flux:text class="font-semibold">{{ $injury->pain_intensity ?? 'n/a' }} / 10</flux:text>
                        </div>
                        <div>
                            <flux:text class="mb-1 block text-sm">Liée à une session</flux:text>
                            <flux:text class="font-semibold">{{ $injury->session_related ? 'Oui' : 'Non' }}</flux:text>
                            @if ($injury->session_related)
                                <flux:text class="mt-1 block text-sm">{{ $injury->session_date?->format('d.m.Y') ?? 'n/a' }}</flux:text>
                            @endif
                        </div>
                        <div>
                            <flux:text class="mb-1 block text-sm">Apparition immédiate</flux:text>
                            <flux:text class="font-semibold">{{ $injury->immediate_onset ? 'Oui' : 'Non' }}</flux:text>
                        </div>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-0">
                <div class="border-b border-gray-200 p-4">
                    <div class="flex items-center">
                        <flux:icon name="chat-bubble-left-right" class="mr-2 h-6 w-6 text-primary-500"></flux:icon>
                        <h2 class="text-xl font-bold">Détails de la déclaration</h2>
                    </div>
                </div>
                <div class="p-6">
                    <div class="space-y-6">
                        <div>
                            <h3 class="mb-2 text-lg font-semibold text-gray-800">
                                Circonstances d'apparition
                            </h3>
                            <div class="prose max-w-none rounded-lg border border-gray-200 bg-gray-50/50 p-4">
                                <p>{{ $injury->onset_circumstances ?? 'Non spécifié.' }}</p>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-2 text-lg font-semibold text-gray-800">
                                Impact sur l'entraînement
                            </h3>
                            <div class="prose max-w-none rounded-lg border border-gray-200 bg-gray-50/50 p-4">
                                <p>{{ $injury->impact_on_training ?? 'Non spécifié.' }}</p>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-2 text-lg font-semibold text-gray-800">
                                Description détaillée
                            </h3>
                            <div class="prose max-w-none rounded-lg border border-gray-200 bg-gray-50/50 p-4">
                                <p>{{ $injury->description ?? 'Aucune description fournie.' }}</p>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-2 text-lg font-semibold text-gray-800">
                                Mon ressenti
                            </h3>
                            <div class="prose max-w-none rounded-lg border border-gray-200 bg-gray-50/50 p-4">
                                <p>{{ $injury->athlete_diagnosis_feeling ?? 'Non spécifié.' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </flux:card>
        </div>

        <div class="lg:col-span-1">
            <div class="sticky top-8 space-y-8">
                <flux:card class="p-0">
                    <div class="border-b border-gray-200 p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <h2 class="text-xl font-bold">Feedbacks médicaux</h2>
                            </div>
                            <flux:button href="{{ route('athletes.injuries.health-events.create', ['hash' => $athlete->hash, 'injury' => $injury->id]) }}" size="sm" variant="outline" icon="plus">
                                Ajouter
                            </flux:button>
                        </div>
                    </div>
                    <div class="p-6">
                        @if ($injury->healthEvents->isEmpty())
                            <div class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50/50 py-10 text-center">
                                <flux:icon name="document-magnifying-glass" class="mx-auto h-12 w-12 text-gray-400"></flux:icon>
                                <p class="mt-4 text-gray-600">Aucun feedback médical.</p>
                                <p class="mt-2 text-sm text-gray-500">Ajoutez un feedback après une consultation.</p>
                            </div>
                        @else
                            <div class="space-y-6">
                                @foreach ($injury->healthEvents->sortByDesc('date') as $healthEvent)
                                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition-all duration-300 hover:shadow-md">
                                        <div class="mb-2">
                                            <flux:link class="text-sm"
                                                :href="route('athletes.health-events.edit', ['hash' => $athlete->hash, 'healthEvent' => $healthEvent])">
                                                {{ $healthEvent->type->getLabel() }}
                                            </flux:link>
                                        </div>
                                        <div class="mb-2 flex items-start justify-between">
                                            <div>
                                                <p class="text-base font-bold text-gray-900">
                                                    {{ $healthEvent->professional?->name }}
                                                </p>
                                                <p class="text-sm text-gray-500">
                                                    {{ $healthEvent->date->format('d.m.Y') }}
                                                    @if ($healthEvent->reported_by_athlete)
                                                        <span class="mx-2">•</span> Par l'athlète
                                                    @endif
                                                    @if ($healthEvent->trainer)
                                                        <span class="mx-2">•</span> Par {{ $healthEvent->trainer->name }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>

                                        @if ($healthEvent->diagnosis)
                                            <div class="mb-3">
                                                <p class="mb-1 text-sm font-medium text-gray-700">Diagnostic:</p>
                                                <div class="prose-sm max-w-none rounded border border-gray-200 bg-gray-50/50 p-3">{{ $healthEvent->diagnosis }}</div>
                                            </div>
                                        @endif

                                        @if ($healthEvent->treatment_plan)
                                            <div class="mb-3">
                                                <p class="mb-1 text-sm font-medium text-gray-700">Plan de traitement:</p>
                                                <div class="prose-sm max-w-none rounded border border-gray-200 bg-gray-50/50 p-3">{{ $healthEvent->treatment_plan }}</div>
                                            </div>
                                        @endif

                                        @if ($healthEvent->training_limitations)
                                            <div class="mb-3">
                                                <p class="mb-1 text-sm font-medium text-gray-700">Limitations d'entraînement:</p>
                                                <div class="prose-sm max-w-none rounded border-l-4 border-yellow-400 bg-yellow-50 p-3 font-medium text-yellow-900">{{ $healthEvent->training_limitations }}</div>
                                            </div>
                                        @endif

                                        @if ($healthEvent->rehab_progress)
                                            <div class="mb-3">
                                                <p class="mb-1 text-sm font-medium text-gray-700">Progrès de rééducation:</p>
                                                <div class="prose-sm max-w-none rounded border border-gray-200 bg-gray-50/50 p-3">{{ $healthEvent->rehab_progress }}</div>
                                            </div>
                                        @endif

                                        @if ($healthEvent->notes)
                                            <div>
                                                <p class="mb-1 text-sm font-medium text-gray-700">Notes complémentaires:</p>
                                                <div class="prose-sm max-w-none rounded border border-gray-200 bg-gray-50/50 p-3">{{ $healthEvent->notes }}</div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </flux:card>
            </div>
        </div>
    </div>
</div>