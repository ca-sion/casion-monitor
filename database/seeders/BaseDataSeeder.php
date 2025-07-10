<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Athlete;
use App\Models\Trainer;
use Illuminate\Database\Seeder;

class BaseDataSeeder extends Seeder
{
    /**
     * Seed the application's database with base data.
     */
    public function run(): void
    {
        // Création de l'utilisateur Admin
        $admin = User::find(1);
        if (! $admin) {
            $admin = User::factory()->create([
                'id'         => 1,
                'name'  => 'Admin',
                'email' => 'admin@example.com',
            ]);
        }

        // Création de l'athlète Arthur (ID 1)
        $arthur = Athlete::find(1);
        if (! $arthur) {
            $arthur = Athlete::factory()->create([
                'id'         => 1,
                'first_name' => 'Arthur',
                'last_name'  => 'de Bretagne',
                'email'      => 'athlete@example.com',
            ]);
        }

        // Création de l'athlète Guenièvre (ID 2)
        $guenievre = Athlete::find(1);
        if (! $guenievre) {
            $guenievre = Athlete::factory()->create([
                'id'         => 2,
                'first_name' => 'Genièvre',
                'last_name'  => 'Lindron',
                'email'      => 'athlete2@example.com',
                'gender'     => 'w',
            ]);
        }

        // Création de l'entraîneur Merlin
        $merlin = Trainer::find(1);
        if (! $merlin) {
            $merlin = Trainer::factory()->create([
                'id'         => 1,
                'first_name' => 'Merlin',
                'last_name'  => "L'enchenteur",
                'email'      => 'trainer@example.com',
            ]);
        }

        // Attacher les athlètes à l'entraîneur
        $merlin->athletes()->attach([$arthur->id, $guenievre->id]);

        $athlete3 = Athlete::find(3);
        if (! $athlete3) {
            $athlete3 = Athlete::factory()->create([
                'id'         => 3,
                'first_name' => 'Athlète 3',
                'last_name'  => 'Masculin',
                'gender'     => 'm',
                'email'      => 'athlete3@example.com',
            ]);
        }
        $merlin->athletes()->syncWithoutDetaching([$athlete3->id]);

        $athlete4 = Athlete::find(4);
        if (! $athlete4) {
            $athlete4 = Athlete::factory()->create([
                'id'         => 4,
                'first_name' => 'Athlète 4',
                'last_name'  => 'Féminine',
                'gender'     => 'w',
                'email'      => 'athlete4@example.com',
            ]);
        }
        $merlin->athletes()->syncWithoutDetaching([$athlete4->id]);

        $this->command->info('Base data seeded: Admin, Arthur, Guenièvre, Athlete ID 3, Athlete ID 4, and Merlin trainer.');
    }
}
