<x-layouts.athlete :title="$athlete->name">
<div class="container mx-auto py-8 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Rapport quotidien</h1>
        <p class="text-gray-500">Date: {{ \Carbon\Carbon::parse($report['end_date'])->locale('fr_CH')->isoFormat('LL') }}</p>
    </div>

    @if(isset($report['global_summary']))
        <div class="lg:col-span-2">
            <x-report-card :section="$report['global_summary']" />
        </div>
    @endif

    @php
        $gamification = $report['sections']['gamification'] ?? null;
        $sections = \Illuminate\Support\Arr::except($report['sections'], 'gamification');
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($sections as $section)
            @if($section)
                <x-report-card :section="$section" />
            @endif
        @endforeach

        @if($gamification)
            <div class="lg:col-span-2">
                <x-report-card :section="$gamification" />
            </div>
        @endif
    </div>

    @if(isset($report['glossary']))
        <div class="lg:col-span-2">
            @include('reports.partials.glossary', ['glossary' => $report['glossary']])
        </div>
    @endif
</div>
</x-layouts.athlete>
