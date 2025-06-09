<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Fondement;
use Faker\Factory as Faker;

class FondementSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('fr_FR');

        for ($i = 0; $i < 5; $i++) {
            Fondement::create([
                'titre' => $faker->words(3, true),
                'texte' => $faker->paragraphs(5, true),
            ]);
        }
    }
}
