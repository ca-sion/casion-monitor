<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Metric;
use App\Models\Athlete;
use App\Models\Trainer;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name'  => 'Admin',
            'email' => 'admin@example.com',
        ]);

        Athlete::factory()->create([
            'first_name' => 'Arthur',
            'last_name'  => 'de Bretagne',
            'email'      => 'athlete@example.com',
        ]);

        Athlete::factory()->create([
            'first_name' => 'Genièvre',
            'last_name'  => 'Lindron',
            'email'      => 'athlete2@example.com',
            'gender'     => 'w',
        ]);

        $trainer = Trainer::factory()->create([
            'first_name' => 'Merlin',
            'last_name'  => "L'enchenteur",
            'email'      => 'trainer@example.com',
        ]);

        $trainer->athletes()->attach([1, 2]);

        Metric::factory(100)->create();

        $this->call(MetricAlertsSeeder::class);
        $this->call(MetricTrendAlertsSeeder::class);

        $trainer->athletes()->attach([3, 4]);
    }
}
