<x-layouts.athlete :title="$athlete->name">
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-4">Rapport hebdomadaire</h1>
    <p class="text-gray-600 mb-8">Semaine se terminant le : {{ \Carbon\Carbon::parse($report['end_date'])->locale('fr_CH')->isoFormat('L') }}</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Load Adherence Section --}}
        @if(isset($report['sections']['load_adherence']))
            @php $section = $report['sections']['load_adherence']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700 mb-4">{!! $section['narrative'] !!}</p>
                @if(isset($section['ratio']))
                <div class="text-center">
                    <p class="text-5xl font-bold">{{ number_format($section['ratio'], 2) }}</p>
                    <p class="text-sm text-gray-500">Ratio CIH/CPH</p>
                </div>
                @endif
            </div>
        @endif

        {{-- ACWR Risk Assessment Section --}}
        @if(isset($report['sections']['acwr_risk_assessment']))
            @php $section = $report['sections']['acwr_risk_assessment']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700 mb-4">{!! $section['narrative'] !!}</p>
                @if(isset($section['acwr_value']))
                <div class="text-center">
                    <p class="text-5xl font-bold @if($section['acwr_value'] >= 1.5) text-red-500 @elseif($section['acwr_value'] >= 1.3) text-yellow-500 @endif">{{ number_format($section['acwr_value'], 2) }}</p>
                    <p class="text-sm text-gray-500">ACWR</p>
                </div>
                @endif
            </div>
        @endif

        {{-- Recovery Debt Section --}}
        @if(isset($report['sections']['recovery_debt']))
            @php $section = $report['sections']['recovery_debt']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700">{!! $section['narrative'] !!}</p>
            </div>
        @endif

        {{-- Day Patterns Section --}}
        @if(isset($report['sections']['day_patterns']))
            @php $section = $report['sections']['day_patterns']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700">{!! $section['narrative'] !!}</p>
            </div>
        @endif

        {{-- Gamification Section --}}
        @if(isset($report['sections']['gamification']))
            @php $section = $report['sections']['gamification']; @endphp
            <div class="md:col-span-2 bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700">{!! $section['narrative'] !!}</p>
            </div>
        @endif
    </div>
</div>
</x-layouts.athlete>
