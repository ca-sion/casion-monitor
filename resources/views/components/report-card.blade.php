@props(['section'])

@php
    $statusClasses = [
        'high_risk' => [
            'bg' => 'bg-red-50',
            'border' => 'border-red-500',
            'text' => 'text-red-800',
            'icon_bg' => 'bg-red-100',
            'icon' => '<svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>'
        ],
        'warning' => [
            'bg' => 'bg-yellow-50',
            'border' => 'border-yellow-500',
            'text' => 'text-yellow-800',
            'icon_bg' => 'bg-yellow-100',
            'icon' => '<svg class="h-6 w-6 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>'
        ],
        'optimal' => [
            'bg' => 'bg-green-50',
            'border' => 'border-green-500',
            'text' => 'text-green-800',
            'icon_bg' => 'bg-green-100',
            'icon' => '<svg class="h-6 w-6 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
        ],
        'low_risk' => [
            'bg' => 'bg-blue-50',
            'border' => 'border-blue-500',
            'text' => 'text-blue-800',
            'icon_bg' => 'bg-blue-100',
            'icon' => '<svg class="h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
        ],
        'neutral' => [
            'bg' => 'bg-gray-50',
            'border' => 'border-gray-400',
            'text' => 'text-gray-800',
            'icon_bg' => 'bg-gray-100',
            'icon' => '<svg class="h-6 w-6 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
        ],
    ];

    $status = $section['status'] ?? 'neutral';
    $classes = $statusClasses[$status];

    // Calcul de la jauge déplacé ici (aucune variable de données n'est modifiée)
    $value = isset($section['main_metric']['value']) ? (float) str_replace('%', '', $section['main_metric']['value']) : 0;
    $min = $section['main_metric']['min'] ?? 0;
    $max = $section['main_metric']['max'] ?? 100;
    $normalizedValue = $max > $min ? (($value - $min) / ($max - $min)) * 100 : 0;
    $normalizedValue = max(0, min(100, $normalizedValue)); // Limite entre 0 et 100
@endphp

<div class="bg-white p-4 sm:p-6 rounded-lg shadow-xl border-l-4 {{ $classes['border'] }}" x-data="{ open: false }">
    <div class="flex flex-col sm:flex-row sm:items-start sm:space-x-4">
        <div class="flex items-start space-x-3 w-full sm:w-auto">
            <div class="flex-shrink-0 h-10 w-10 sm:h-12 sm:w-12 rounded-full flex items-center justify-center {{ $classes['icon_bg'] }}">
                {!! $classes['icon'] !!}
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center space-x-2">
                    <h3 class="text-base sm:text-lg font-bold text-gray-900">{{ data_get($section, 'title') }}</h3>
                    @if(isset($section['explanation']))
                        <button @click="open = !open" class="text-gray-400 hover:text-gray-600 focus:outline-none transition duration-150 ease-in-out" aria-label="Afficher l'explication">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    @endif
                </div>
                <p class="text-sm font-semibold {{ $classes['text'] }} mt-0.5">{{ $section['summary'] }}</p>
            </div>
        </div>

        @if(isset($section['main_metric']) && $section['main_metric']['value'] !== 'n/a')
            <div class="mt-4 sm:mt-0 sm:ml-auto flex-shrink-0 text-left sm:text-right w-full sm:w-auto">
                <div class="flex items-center justify-between sm:justify-end">
                    <p class="text-3xl font-extrabold text-gray-900">{{ $section['main_metric']['value'] }}</p>
                </div>
                <p class="text-xs sm:text-sm text-gray-500">{{ $section['main_metric']['label'] }}</p>

                @if(isset($section['main_metric']['type']) && $section['main_metric']['type'] === 'gauge')
                    <div class="relative w-full sm:w-32 h-3 bg-gray-200 rounded-full mt-2 overflow-visible">

                        @foreach($section['main_metric']['ranges'] as $rangeStatus => $rangeValues)
                            @php
                                $rangeMin = $rangeValues[0];
                                $rangeMax = $rangeValues[1];
                                $normalizedRangeMin = $max > $min ? (($rangeMin - $min) / ($max - $min)) * 100 : 0;
                                $normalizedRangeMax = $max > $min ? (($rangeMax - $min) / ($max - $min)) * 100 : 0;
                                $rangeWidth = $normalizedRangeMax - $normalizedRangeMin;
                                $rangeLeft = $normalizedRangeMin;

                                $rangeColorClass = match($rangeStatus) {
                                    'high_risk' => 'bg-red-500',
                                    'warning' => 'bg-yellow-500',
                                    'optimal' => 'bg-green-500',
                                    'low_risk' => 'bg-blue-500',
                                    'neutral' => 'bg-gray-400',
                                    default => 'bg-gray-300',
                                };
                            @endphp
                            <div class="absolute h-full {{ $rangeColorClass }}"
                                 style="left: {{ $rangeLeft }}%; width: {{ $rangeWidth }}%;">
                            </div>
                        @endforeach

                        <div class="absolute top-1/2 -translate-y-1/2 h-4 w-4 rounded-full bg-gray-900 border-2 border-white shadow transition-all duration-300 ease-out"
                             style="left: {{ $normalizedValue }}%; transform: translateX(-50%);">
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="mt-4 sm:pl-16">
        @if(isset($section['explanation']))
            <div x-show="open" x-transition class="mt-4">
                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                    <p class="text-sm font-semibold text-gray-700">🔍 Qu'est-ce que c'est ?</p>
                    <p class="text-sm text-gray-600 mt-1">{{ $section['explanation'] }}</p>
                </div>
            </div>
        @endif

        @if(!empty($section['points']))
            <div class="mt-4">
                <ul class="space-y-2">
                    @foreach($section['points'] as $point)
                        <li class="flex items-start space-x-3">
                            <div class="flex-shrink-0 pt-0.5">
                                @php
                                    $pointStatus = $point['status'] ?? 'neutral';
                                    $pointClasses = $statusClasses[$pointStatus];
                                @endphp
                                <span class="h-5 w-5 flex items-center justify-center rounded-full {{ $pointClasses['icon_bg'] }}">
                                    {!! Str::substr($pointClasses['icon'], 0, -6) . ' class="h-4 w-4 ' . $pointClasses['text'] . '"' . Str::substr($pointClasses['icon'], -6) !!}
                                </span>
                            </div>
                            <p class="text-gray-700 text-sm leading-relaxed">{!! $point['text'] !!}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(isset($section['recommendation']) && !empty($section['recommendation']))
            <div class="mt-4">
                <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                    <p class="text-sm font-semibold text-yellow-800">💡 Recommandation :</p>
                    <p class="text-sm text-yellow-700 mt-1">{{ $section['recommendation'] }}</p>
                </div>
            </div>
        @endif
    </div>
</div>
