<x-layouts.athlete :title="$athlete->name">
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-4">Rapport quotidien</h1>
    <p class="text-gray-600 mb-8">Date: {{ \Carbon\Carbon::parse($report['end_date'])->locale('fr_CH')->isoFormat('L') }}</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Recommendation Section --}}
        @if(isset($report['sections']['recommendation']))
            @php $section = $report['sections']['recommendation']; @endphp
            <div class="md:col-span-2 bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4" role="alert">
                <p class="font-bold">{{ $section['title'] }}</p>
                <p>{!! $section['narrative'] !!}</p>
            </div>
        @endif

        {{-- Readiness Status Section --}}
        @if(isset($report['sections']['readiness_status']))
            @php $section = $report['sections']['readiness_status']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700 mb-4">{!! $section['narrative'] !!}</p>
                <div class="text-center">
                    <p class="text-5xl font-bold text-{{ $section['status'] === 'red' ? 'red' : ($section['status'] === 'orange' || $section['status'] === 'yellow' ? 'yellow' : 'green') }}-500">{{ $section['score'] }}/100</p>
                    <p class="text-sm text-gray-500">Score de Readiness</p>
                </div>
                {{-- @if(!empty($section['details']))
                    <div class="mt-4">
                        <h3 class="font-semibold">Détails :</h3>
                        <ul class="list-disc list-inside">
                            @foreach($section['details'] as $detail)
                                <li>{{ $detail['metric_short_label'] }} : {{ $detail['message'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif --}}
            </div>
        @endif

        {{-- Alerts and Inconsistencies Section --}}
        @if(isset($report['sections']['alerts_and_inconsistencies']))
            @php $section = $report['sections']['alerts_and_inconsistencies']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700">{!! $section['narrative'] !!}</p>
            </div>
        @endif

        {{-- Inter-Day Correlation Section --}}
        @if(isset($report['sections']['j_minus_1_correlation']))
            @php $section = $report['sections']['j_minus_1_correlation']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700">{!! $section['narrative'] !!}</p>
            </div>
        @endif

        {{-- Gamification Section --}}
        @if(isset($report['sections']['gamification']))
            @php $section = $report['sections']['gamification']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700">{!! $section['narrative'] !!}</p>
            </div>
        @endif
    </div>
</div>
</x-layouts.athlete>
