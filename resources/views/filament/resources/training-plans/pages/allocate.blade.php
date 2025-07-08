<x-filament-panels::page>
    <div x-data="trainingCalendar()" x-init="init()">

        {{-- Nouveau Tableau Horizontal des Semaines --}}
        @if(count($this->weeks) > 0)
            <div class="overflow-x-auto">
                <div class="flex space-x-2">
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
                            <div class="text-xs text-center text-gray-500">{{ \Carbon\Carbon::parse($week['start_date'])->isoFormat('DD.MM') }}</div>
                            
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
                                       class="absolute h-1 w-36 transform -rotate-90 origin-center rounded-lg appearance-none cursor-pointer"
                                       style="writing-mode: bt-lr; background: linear-gradient(to right, {{ $week['intensity_planned'] === null ? 'rgb(203, 213, 225)' : 'rgb(' . (203 - ($week['intensity_planned'] * 0.75)) . ', ' . (213 - ($week['intensity_planned'] * 0.75)) . ', ' . (225 - ($week['intensity_planned'] * 0.75)) . ')' }} 0%, rgb(128, 0, 128) {{ $week['intensity_planned'] ?? 0 }}%, rgb(203, 213, 225) {{ $week['intensity_planned'] ?? 0 }}%);">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <p class="mt-4 text-gray-600">Aucune semaine générée pour ce plan d'entraînement. Assurez-vous que les dates de début et de fin sont définies.</p>
        @endif

    </div>

    <script>

        function trainingCalendar() {
            return {
                init() {
                    // Pas de génération de calendrier annuel ici
                },

                selectWeek(startDate) {
                    console.log('Selected week starting:', startDate);
                    @this.call('selectWeek', startDate);
                }
            };
        }
    </script>
</x-filament-panels::page>
