<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Actualite;
use Faker\Factory as Faker;

class ActualiteSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('fr_FR');

        for ($i = 0; $i < 10; $i++) {
            Actualite::create([
                'titre' => $faker->sentence(6, true),
                'description' => $faker->paragraphs(3, true),
            ]);
        }
    }
}
