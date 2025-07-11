<?php

namespace Database\Seeders;

use App\Models\Athlete;
use App\Models\TrainingPlan;
use Illuminate\Database\Seeder;
use App\Models\TrainingPlanWeek;

class TrainingPlanSeeder extends Seeder
{
    /**
     * Seed the application's database with base data.
     */
    public function run(): void
    {
        $plan1 = TrainingPlan::find(1);
        if (! $plan1) {
            $plan1 = TrainingPlan::factory()->create([
                'id'         => 1,
                'name'       => 'Plan A',
                'start_date' => now()->subMonths(6)->startOfMonth()->toDateString(),
                'end_date'   => now()->addMonths(6)->endOfMonth()->toDateString(),
            ]);
        }

        $plan2 = TrainingPlan::find(2);
        if (! $plan2) {
            $plan2 = TrainingPlan::factory()->create([
                'id'         => 2,
                'name'       => 'Plan B',
                'start_date' => now()->subMonths(1)->startOfMonth()->toDateString(),
                'end_date'   => now()->addMonths(12)->endOfMonth()->toDateString(),
            ]);
        }

        // Création de semaines réalistes pour le Plan A (48 semaines)
        $plan1->weeks()->delete();
        $plan1->weeks()->createMany(
            collect(range(1, 48))->map(function ($i) {
                return [
                    'start_date'        => now()->startOfMonth()->subMonths(6)->startOfWeek()->addWeeks($i - 1)->toDateString(),
                    'week_number'       => $i,
                    'volume_planned'    => rand(1, 5), // Volume réaliste
                    'intensity_planned' => rand(60, 90), // Intensité réaliste
                ];
            })->toArray()
        );

        // Création de semaines réalistes pour le Plan B (12 semaines)
        $plan2->weeks()->delete();
        $plan2->weeks()->createMany(
            collect(range(1, 12))->map(function ($i) {
                return [
                    'start_date'        => now()->startOfMonth()->subMonths(1)->startOfWeek()->addWeeks($i - 1)->toDateString(),
                    'week_number'       => $i,
                    'volume_planned'    => rand(1, 5), // Volume réaliste
                    'intensity_planned' => rand(65, 95), // Intensité réaliste
                ];
            })->toArray()
        );

        $athletes = Athlete::all();
        if ($athletes) {
            $plan1->athletes()->attach($athletes);
        }

        $this->command->info('Training plan data seeded: Plan A, Plan B.');
    }
}
