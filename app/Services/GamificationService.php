<?php

namespace App\Services;

use App\Models\Athlete;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;

class GamificationService
{
    private const POINTS_PER_ENTRY = 10;
    private const POINTS_FOR_FEEDBACK = 5;

    private const LEVELS = [
        3000 => 'Légende',
        1500 => 'Expert',
        750 => 'Confirmé',
        250 => 'Régulier',
        0 => 'Débutant',
    ];

    /**
     * Process a new data entry for an athlete and update all gamification metrics.
     *
     * @param Athlete $athlete The athlete to update.
     * @param Carbon $entryDate The date of the new entry.
     * @param array $formData The submitted form data to check for completeness.
     */
    public function processEntry(Athlete $athlete, Carbon $entryDate, array $formData): void
    {
        $metadata = $athlete->metadata;
        $gamification = $metadata['gamification'] ?? $this->getDefaultGamificationData();

        // Prevent processing if an entry for the same day already exists.
        $lastEntryDate = $gamification['last_entry_date'] ? Carbon::parse($gamification['last_entry_date'])->startOfDay() : null;
        $currentEntryDate = $entryDate->copy()->startOfDay();
        if ($lastEntryDate && $lastEntryDate->isSameDay($currentEntryDate)) {
            return;
        }

        // 1. Update Streak with grace period
        $gamification = $this->updateStreak($gamification, $currentEntryDate, $lastEntryDate);

        // 2. Add Points
        $gamification = $this->addPoints($gamification, $formData);

        // 3. Update Level
        $gamification = $this->updateLevel($gamification);

        // 4. Award Badges
        $gamification = $this->awardBadges($gamification);

        // Save all changes
        $metadata['gamification'] = $gamification;
        $athlete->metadata = $metadata;
        $athlete->save();
    }

    /**
     * Update the streak with a 2-day grace period.
     */
    private function updateStreak(array $gamification, Carbon $currentEntryDate, ?Carbon $lastEntryDate): array
    {
        if ($lastEntryDate === null) {
            $gamification['current_streak'] = 1;
        } else {
            $daysDifference = $currentEntryDate->diffInDays($lastEntryDate);

            if ($daysDifference <= 2) {
                // Streak continues
                $gamification['current_streak']++;
            } else {
                // Streak is broken
                $gamification['current_streak'] = 1;
            }
        }

        $gamification['last_entry_date'] = $currentEntryDate->toDateString();
        if ($gamification['current_streak'] > ($gamification['longest_streak'] ?? 0)) {
            $gamification['longest_streak'] = $gamification['current_streak'];
        }

        return $gamification;
    }

    /**
     * Add points for the new entry.
     */
    private function addPoints(array $gamification, array $formData): array
    {
        $points = self::POINTS_PER_ENTRY;

        // Add bonus for feedback
        if (!empty(Arr::get($formData, 'post_session_sensation')) || !empty(Arr::get($formData, 'pre_session_goals'))) {
            $points += self::POINTS_FOR_FEEDBACK;
        }

        $gamification['points'] = ($gamification['points'] ?? 0) + $points;
        return $gamification;
    }

    /**
     * Update the athlete's level based on their total points.
     */
    private function updateLevel(array $gamification): array
    {
        $points = $gamification['points'] ?? 0;
        foreach (self::LEVELS as $threshold => $level) {
            if ($points >= $threshold) {
                $gamification['level'] = $level;
                break;
            }
        }
        return $gamification;
    }

    /**
     * Award new badges based on current stats.
     */
    private function awardBadges(array $gamification): array
    {
        $badges = $gamification['badges'] ?? [];

        // Streak badges
        if ($gamification['current_streak'] >= 10 && !in_array('streak_10', $badges)) {
            $badges[] = 'streak_10';
        }
        if ($gamification['current_streak'] >= 30 && !in_array('streak_30', $badges)) {
            $badges[] = 'streak_30';
        }

        // Points badges
        if ($gamification['points'] >= 1000 && !in_array('points_1000', $badges)) {
            $badges[] = 'points_1000';
        }

        $gamification['badges'] = $badges;
        return $gamification;
    }

    /**
     * Get the default structure for gamification metadata.
     */
    private function getDefaultGamificationData(): array
    {
        return [
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_entry_date' => null,
            'points' => 0,
            'level' => 'Débutant',
            'badges' => [],
        ];
    }
}