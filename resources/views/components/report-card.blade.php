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
@endphp

<div class="bg-white p-6 rounded-lg shadow-md border-l-4 {{ $classes['border'] }}">
    <div class="flex items-start space-x-4">
        <div class="flex-shrink-0 h-12 w-12 rounded-full flex items-center justify-center {{ $classes['icon_bg'] }}">
            {!! $classes['icon'] !!}
        </div>
        <div class="flex-1">
            <h3 class="text-lg font-bold text-gray-900">{{ $section['title'] }}</h3>
            <p class="text-sm font-semibold {{ $classes['text'] }}">{{ $section['summary'] }}</p>
        </div>
        @if(isset($section['main_metric']) && $section['main_metric']['value'] !== 'N/A')
            <div class="text-right">
                <p class="text-3xl font-bold text-gray-900">{{ $section['main_metric']['value'] }}</p>
                <p class="text-sm text-gray-500">{{ $section['main_metric']['label'] }}</p>
            </div>
        @endif
    </div>

    @if(!empty($section['points']))
        <div class="mt-4 pl-16">
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
                        <p class="text-gray-700 text-sm">{!! $point['text'] !!}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(isset($section['recommendation']) && !empty($section['recommendation']))
        <div class="mt-4 pl-16">
            <div class="bg-gray-50 p-3 rounded-lg">
                <p class="text-sm font-semibold text-gray-800">Recommandation :</p>
                <p class="text-sm text-gray-600">{{ $section['recommendation'] }}</p>
            </div>
        </div>
    @endif
</div>
