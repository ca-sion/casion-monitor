<x-layouts.athlete :title="$athlete->name">
<div class="container mx-auto py-8 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Rapport hebdomadaire</h1>
        <p class="text-gray-500">Semaine se terminant le : {{ \Carbon\Carbon::parse($report['end_date'])->locale('fr_CH')->isoFormat('LL') }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($report['sections'] as $section)
            @if($section)
                <x-report-card :section="$section" />
            @endif
        @endforeach
    </div>

    @if(isset($report['glossary']))
        <div class="lg:col-span-2">
            @include('reports.partials.glossary', ['glossary' => $report['glossary']])
        </div>
    @endif
</div>
</x-layouts.athlete>
