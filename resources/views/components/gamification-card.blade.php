@props(['gamificationData'])

@if ($gamificationData)
    @php
        $levels = [
            'Légende' => 3000,
            'Expert' => 1500,
            'Confirmé' => 750,
            'Régulier' => 250,
            'Débutant' => 0,
        ];
        $currentLevel = $gamificationData['level'] ?? 'Débutant';
        $currentPoints = $gamificationData['points'] ?? 0;
        
        $currentLevelPoints = $levels[$currentLevel];
        $nextLevel = null;
        $nextLevelPoints = null;
        
        $levelKeys = array_keys($levels);
        $currentLevelIndex = array_search($currentLevel, $levelKeys);
        
        if ($currentLevelIndex > 0) {
            $nextLevel = $levelKeys[$currentLevelIndex - 1];
            $nextLevelPoints = $levels[$nextLevel];
        }
        
        $progressPercentage = 100;
        if ($nextLevelPoints) {
            $pointsForNextLevel = $nextLevelPoints - $currentLevelPoints;
            $pointsInCurrentLevel = $currentPoints - $currentLevelPoints;
            $progressPercentage = $pointsForNextLevel > 0 ? ($pointsInCurrentLevel / $pointsForNextLevel) * 100 : 100;
        }
    @endphp
    <div class="mb-6">
        <flux:card>
            {{-- Level & Progress --}}
            <div class="p-2">
                <div class="flex justify-between items-center mb-1">
                    <div class="flex items-center gap-2">
                        <p class="font-bold text-gray-800 dark:text-gray-200">{{ $currentLevel }}</p>
                        @if (!empty($gamificationData['badges']))
                            <div class="flex gap-1">
                                @foreach ($gamificationData['badges'] as $badge)
                                    <span class="text-lg" title="{{ $badge }}">{{ match($badge) {
                                        'streak_10' => '🔟',
                                        'streak_30' => '🗓️',
                                        'points_1000' => '💰',
                                        default => '🏅'
                                    } }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $currentPoints }} pts</p>
                </div>
                @if ($nextLevel)
                    <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: {{ $progressPercentage }}%"></div>
                    </div>
                    <p class="text-xs text-right text-gray-500 dark:text-gray-400 mt-1">Prochain niveau : {{ $nextLevel }} ({{ $nextLevelPoints }} pts)</p>
                @else
                    <p class="text-xs text-center text-blue-500 font-semibold mt-2">Niveau maximum atteint !</p>
                @endif
            </div>

            {{-- Streaks --}}
            <div class="grid grid-cols-2 divide-x divide-gray-200 dark:divide-gray-700 text-center border-t border-gray-200 dark:border-gray-700">
                <div class="p-2">
                    <p class="text-xl font-bold">🔥 {{ $gamificationData['current_streak'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Série en cours</p>
                </div>
                <div class="p-2">
                    <p class="text-xl font-bold">🏆 {{ $gamificationData['longest_streak'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Meilleure série</p>
                </div>
            </div>
        </flux:card>
    </div>
@endif
