<x-filament-panels::page>
    <div x-data="trainingCalendar({ initialTrainingPlans: @js($this->getTrainingPlans()) })" x-init="init()">
        <h2 class="text-2xl font-bold mb-4">Planification des Entraînements</h2>

        <div class="mb-6">
            <label for="trainingPlanSelect" class="block text-sm font-medium text-gray-700">Sélectionner un Plan d'Entraînement :</label>
            <select id="trainingPlanSelect"
                    wire:model.live="selectedTrainingPlanId"
                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                <option value="">-- Sélectionner un plan --</option>
                @foreach($this->getTrainingPlans() as $plan)
                    <option value="{{ $plan['id'] }}">{{ $plan['name'] }}</option>
                @endforeach
            </select>
            <button wire:click="createNewTrainingPlan()" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Créer un nouveau plan</button>
        </div>

        {{-- Nouveau Tableau Horizontal des Semaines --}}
        @if($this->selectedTrainingPlanId && count($this->weeks) > 0)
            <div class="overflow-x-auto">
                <div class="flex space-x-4 p-4 bg-gray-50 rounded-lg shadow-inner">
                    @foreach($this->weeks as $week)
                        <div class="flex-none w-fit p-3 border rounded-lg shadow-sm bg-white {{ $week['exists'] ? 'border-blue-400' : '' }} flex flex-col items-center justify-between">
                            {{-- Barres de Volume (1-5) - Vertical --}}
                            <div class="flex flex-col-reverse h-24 bg-gray-200 rounded overflow-hidden w-8 mb-2">
                                @for ($i = 1; $i <= 5; $i++)
                                    <div class="flex-1 w-full cursor-pointer {{ ($week['volume_planned'] ?? 0) >= $i ? 'bg-blue-500' : 'bg-gray-300' }} {{ $i < 5 ? 'mb-0.5' : '' }}"
                                         wire:click="updateWeekData('{{ $week['start_date'] }}', 'volume_planned', {{ $i }})"></div>
                                @endfor
                            </div>
                            <p class="text-xs text-center text-gray-500 mb-2">Vol: {{ $week['volume_planned'] ?? 'N/A' }} / 5</p>

                            <div class="font-semibold text-center mb-2">S{{ $week['week_number'] }}</div>
                            <div class="text-xs text-center text-gray-500">{{ \Carbon\Carbon::parse($week['start_date'])->isoFormat('l') }}</div>
                            
                            {{-- Bouton pour affiner les jours (sera un modal plus tard) --}}
                            <button class="mt-2 px-2 py-1 text-white rounded-md text-xs hover:bg-gray-50 mb-2"
                                    wire:click="selectWeekForDailyRefinement('{{ $week['start_date'] }}')">
                                ⚙️
                            </button>

                            <p class="text-xs text-center text-gray-500 mb-2">Int: {{ $week['intensity_planned'] ?? 'N/A' }}%</p>

                            {{-- Slider d'Intensité (1-100) - Vertical --}}
                            <div class="relative h-36 w-8 flex items-center justify-center">
                                <input type="range" min="0" max="100" step="1"
                                       wire:model.live="weeks.{{ $loop->index }}.intensity_planned"
                                       wire:change="updateWeekData('{{ $week['start_date'] }}', 'intensity_planned', $event.target.value)"
                                       class="absolute h-1 w-36 transform -rotate-90 origin-center bg-orange-500 rounded-lg appearance-none cursor-pointer"
                                       style="writing-mode: bt-lr;">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @elseif($this->selectedTrainingPlanId)
            <p class="mt-4 text-gray-600">Aucune semaine générée pour ce plan d'entraînement. Assurez-vous que les dates de début et de fin sont définies.</p>
        @else
            <p class="mt-4 text-gray-600">Sélectionnez un plan d'entraînement pour commencer la planification.</p>
        @endif

    </div>

    <script>

        function trainingCalendar(config) {
            return {
                trainingPlans: config.initialTrainingPlans || [], // Initialized from Livewire
                selectedTrainingPlanId: @entangle('selectedTrainingPlanId'), // Bind directly to Livewire property
                selectedWeek: @entangle('selectedWeek'), // Bind selectedWeek from Livewire

                // Watch for changes in selectedTrainingPlanId
                init() {
                    this.$watch('selectedTrainingPlanId', (value) => {
                        console.log('selectedTrainingPlanId changed to:', value);
                    });
                },

                init() {
                    // Pas de génération de calendrier annuel ici
                },

                // selectDay et generateCalendar ne sont plus nécessaires
                // selectDay(day) { ... }
                // generateCalendar() { ... }

                selectWeek(startDate) {
                    console.log('Selected week starting:', startDate);
                    @this.call('selectWeek', startDate);
                }
            };
        }
    </script>
</x-filament-panels::page>
