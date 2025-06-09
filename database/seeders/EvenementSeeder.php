<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Evenement;
use Faker\Factory as Faker;

class EvenementSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('fr_FR');

        for ($i = 0; $i < 10; $i++) {
            Evenement::create([
                'titre' => $faker->sentence(4, true),
                'description' => $faker->paragraphs(2, true),
                'date' => $faker->dateTimeBetween('+1 days', '+1 year'),
            ]);
        }
    }
}
