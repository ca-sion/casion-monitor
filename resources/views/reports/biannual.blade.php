<x-layouts.athlete :title="$athlete->name">
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-4">Rapport semestriel</h1>
    <p class="text-gray-600 mb-8">Semestre se terminant le : {{ \Carbon\Carbon::parse($report['end_date'])->locale('fr_CH')->isoFormat('L') }}</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Long Term Adaptation Section --}}
        @if(isset($report['sections']['long_term_adaptation']))
            @php $section = $report['sections']['long_term_adaptation']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700">{!! $section['narrative'] !!}</p>
            </div>
        @endif

        {{-- Efficiency Gap Analysis Section --}}
        @if(isset($report['sections']['efficiency_gap_analysis']))
            @php $section = $report['sections']['efficiency_gap_analysis']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700">{!! $section['narrative'] !!}</p>
            </div>
        @endif

        {{-- Injury Pattern Section --}}
        @if(isset($report['sections']['injury_pattern']))
            @php $section = $report['sections']['injury_pattern']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700">{!! $section['narrative'] !!}</p>
            </div>
        @endif

        {{-- Pacing Strategy Section --}}
        @if(isset($report['sections']['pacing_strategy']))
            @php $section = $report['sections']['pacing_strategy']; @endphp
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
