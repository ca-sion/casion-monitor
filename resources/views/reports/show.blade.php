<x-layouts.athlete :title="$athlete->name">
    <div class="container mx-auto space-y-6 py-8">
        <div>
            <flux:heading class="text-3xl font-bold mb-2"
                level="1"
                size="xl">Rapport d'analyse</flux:heading>
            <flux:text>Date : {{ $endDate->locale('fr_CH')->isoFormat('LL') }}</flux:text>
            <x-flux::button class="mt-2"
                href="{{ route('athletes.reports.ai', ['hash' => $athlete->hash]) }}"
                tag="a"
                variant="primary"
                color="blue"
                outlined="true"
                icon="chart-bar-square">Analyse par l'IA</x-flux::button>
        </div>

        @foreach ($reports as $reportType => $report)
            <div class="mb-8">
                <flux:heading class="mb-4"
                    level="3"
                    size="lg">
                    {{ match ($reportType) {
                        'daily' => 'Quotidien',
                        'weekly' => 'Hebdomadaire',
                        'monthly' => 'Mensuel',
                        'biannual' => 'Semestriel',
                        'narrative' => 'Résumé',
                        default => 'Inconnu',
                    } }}
                </flux:heading>

                <div class="my-4 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    @foreach ($report['sections'] as $section)
                        @if ($section)
                            <x-report-card :section="$section" />
                        @endif
                    @endforeach
                    @if (data_get($report, 'content'))
                        <div class="prose prose-sm max-w-none lg:col-span-2">
                            {!! str(data_get($report, 'content'))->markdown() !!}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        @if ($glossary)
            <div class="lg:col-span-2">
                @include('reports.partials.glossary', ['glossary' => $glossary])
            </div>
        @endif
    </div>
</x-layouts.athlete>
