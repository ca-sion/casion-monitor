<x-layouts.athlete :title="$athlete->name">
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-4">Rapport mensuel</h1>
    <p class="text-gray-600 mb-8">Mois se terminant le : {{ \Carbon\Carbon::parse($report['end_date'])->locale('fr_CH')->isoFormat('L') }}</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Damping Summary Section --}}
        @if(isset($report['sections']['damping_summary']))
            @php $section = $report['sections']['damping_summary']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700">{!! $section['narrative'] !!}</p>
            </div>
        @endif

        {{-- Sleep Impact Section --}}
        @if(isset($report['sections']['sleep_impact']))
            @php $section = $report['sections']['sleep_impact']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700">{!! $section['narrative'] !!}</p>
            </div>
        @endif

        {{-- Pain Hotspot Section --}}
        @if(isset($report['sections']['pain_hotspot']))
            @php $section = $report['sections']['pain_hotspot']; @endphp
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-2">{{ $section['title'] }}</h2>
                <p class="text-gray-700">{!! $section['narrative'] !!}</p>
            </div>
        @endif

        {{-- Menstrual Summary Section --}}
        @if(isset($report['sections']['menstrual_summary']))
            @php $section = $report['sections']['menstrual_summary']; @endphp
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
