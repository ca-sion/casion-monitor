<x-layouts.athlete :title="$athlete->name">
    <div class="container mx-auto space-y-6 py-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Rapport</h1>
            <p class="text-gray-500">Date : {{ \Carbon\Carbon::parse($report['end_date'])->locale('fr_CH')->isoFormat('LL') }}</p>
            <x-filament::button tag="a" href="{{ route('athletes.reports.biannual', ['hash' => $athlete->hash]) }}" size="sm" class="mt-2" outlined="true">Voir le rapport semestriel</x-filament::button>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach ($report['sections'] as $section)
                @if ($section)
                    <x-report-card :section="$section" />
                @endif
            @endforeach
        </div>

        @if (isset($report['glossary']))
            <div class="lg:col-span-2">
                @include('reports.partials.glossary', ['glossary' => $report['glossary']])
            </div>
        @endif
    </div>
</x-layouts.athlete>
