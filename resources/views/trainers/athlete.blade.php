<x-layouts.trainer :title="$athlete->name">
    <flux:heading size="xl" level="1">{{ $athlete->name }}</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Voici les métriques.</flux:text>

    <flux:separator variant="subtle" />
    
    <flux:table class="my-4">
        <flux:table.columns>
            <flux:table.column>Date</flux:table.column>
            @foreach ($display_table_metric_types as $metricType)
                <flux:table.column>{{ $metricType->getLabelShort() }}</flux:table.column>
            @endforeach
            <flux:table.column class="text-center">Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($daily_metrics_history as $rowData)
                <flux:table.row>
                    <flux:table.cell>
                        {{ $rowData['date_formatted'] }}
                        ({{ $rowData['day_of_week'] }})
                    </flux:table.cell>
                    @foreach ($display_table_metric_types as $metricType)
                        <flux:table.cell>
                            @if (isset($rowData['metrics'][$metricType->value]))
                                <flux:badge size="xs" color="zinc">
                                    <span class="font-medium">{{ $metricType->getLabelShort() }}:</span>
                                    {{ $rowData['metrics'][$metricType->value] }}
                                </flux:badge>
                            @else
                                N/A
                            @endif
                        </flux:table.cell>
                    @endforeach
                    <flux:table.cell class="text-center">
                        @if ($rowData['edit_link'])
                            <flux:link href="{{ $rowData['edit_link'] }}">Modifier</flux:link>
                        @else
                            N/A
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="{{ count($display_table_metric_types) + 2 }}" class="text-center text-zinc-500 py-4">
                        Aucune entrée de métrique trouvée pour cet athlète.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

</x-layouts.trainer>