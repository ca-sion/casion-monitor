<?php

namespace Database\Seeders;

use App\Models\Metric;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Appeler le seeder de données de base en premier
        $this->call(BaseDataSeeder::class);

        // Optionnel: Créer quelques métriques aléatoires pour d'autres athlètes ou juste pour voir des données
        // Metric::factory(100)->create();

        // Appeler les seeders de scénarios spécifiques à chaque athlète
        // Vous pouvez commenter/décommenter ces lignes pour choisir quels athlètes recevront des données de scénario.
        $this->call(Athlete1RealisticScenariosSeeder::class);
        // $this->call(Athlete1ScenariosSeeder::class);
        $this->call(Athlete2ScenariosSeeder::class);
        $this->call(Athlete3ScenariosSeeder::class);
        $this->call(Athlete4ScenariosSeeder::class);

        $this->command->info('Tous les seeders ont été exécutés.');
    }
}
