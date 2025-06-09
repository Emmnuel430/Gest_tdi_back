<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Parachiot;
use Faker\Factory as Faker;

class ParachaSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('fr_FR');

        for ($i = 0; $i < 6; $i++) {
            Parachiot::create([
                'titre' => $faker->words(3, true),
                'resume' => $faker->optional()->sentence(10),
                'contenu' => $faker->paragraphs(4, true),
                'date_lecture' => $faker->dateTimeBetween('+5 days', '+2 months')->format('Y-m-d'),
                'fichier' => null, // On ne simule pas d’upload dans un seeder simple
            ]);
        }
    }
}
