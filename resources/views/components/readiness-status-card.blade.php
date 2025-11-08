@props(['readinessStatus'])

@if ($readinessStatus)
    @php
        $readinessColor = match ($readinessStatus['level']) {
            'green' => 'emerald',
            'yellow' => 'lime',
            'orange' => 'amber',
            'red' => 'rose',
            'neutral' => 'zinc',
            default => 'zinc',
        };
        $readinessBgColor = match ($readinessStatus['level']) {
            'green' => 'bg-emerald-50/50 dark:bg-emerald-950/50',
            'yellow' => 'bg-lime-50/50 dark:bg-lime-950/50',
            'orange' => 'bg-amber-50/50 dark:bg-amber-950/50',
            'red' => 'bg-rose-50/50 dark:bg-rose-950/50',
            'neutral' => 'bg-zinc-50/50 dark:bg-zinc-950/50',
            default => 'bg-zinc-50/50 dark:bg-zinc-950/50',
        };
        $readinessBorderColor = match ($readinessStatus['level']) {
            'green' => 'border-emerald-400',
            'yellow' => 'border-lime-400',
            'orange' => 'border-amber-400',
            'red' => 'border-rose-400',
            'neutral' => 'border-zinc-400',
            default => 'border-zinc-400',
        };
    @endphp

    <div class="mb-6 flex flex-col gap-2" id="readiness">
        <div class="{{ $readinessBorderColor }} {{ $readinessBgColor }} rounded-md border p-6 shadow-lg">
            <flux:text class="text-sm font-semibold">Readiness: <span class="font-bold">{{ $readinessStatus['readiness_score'] }}</span></flux:text>
            <flux:badge class="whitespace-normal! mt-1"
                size="sm"
                inset="top bottom"
                color="{{ $readinessColor }}">
                {{ $readinessStatus['message'] }}
            </flux:badge>
            <div>
                <flux:text class="whitespace-normal! mt-2 text-xs">
                    <span class="font-medium">Recommandation:</span>
                    {{ $readinessStatus['recommendation'] }}
                </flux:text>
            </div>
        </div>
    </div>
@endif
