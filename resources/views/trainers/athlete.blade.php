<x-layouts.trainer :title="$athlete->name">
    <flux:heading size="xl" level="1">{{ $athlete->name }}</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Voici les métriques.</flux:text>

    <flux:separator variant="subtle" />
    
    <flux:table class="my-4">
        <flux:table.columns>
            <flux:table.column>Date</flux:table.column>
            <flux:table.column>VFC/HRV</flux:table.column>
            <flux:table.column>Fatigue matin</flux:table.column>
            <flux:table.column>Eval. fatigue après</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($athlete->metricsByDates as $date => $metricDates)
                <flux:table.row>
                    <flux:table.cell>
                        {{ $metricDates->first()->date->locale('fr_CH')->isoFormat('L') }}
                        ({{ $metricDates->first()->date->locale('fr_CH')->isoFormat('dddd') }})
                    </flux:table.cell>
                    <flux:table.cell>{{ $metricDates->where('metric_type', 'morning_hrv')?->first()?->value }}</flux:table.cell>
                    <flux:table.cell>{{ $metricDates->where('metric_type', 'morning_general_fatigue')?->first()?->value }}</flux:table.cell>
                    <flux:table.cell>{{ $metricDates->where('metric_type', 'post_session_subjective_fatigue')?->first()?->value }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

</x-layouts.trainer>