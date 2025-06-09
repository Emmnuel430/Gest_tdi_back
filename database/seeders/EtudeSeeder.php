<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Etude;
use Faker\Factory as Faker;

class EtudeSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('fr_FR');

        for ($i = 0; $i < 10; $i++) {
            Etude::create([
                'titre' => $faker->sentence(5, true),
                'verset' => $faker->optional()->regexify('^[A-Z][a-z]+ \d+:\d+$'), // Ex: Jean 3:16
                'texte' => $faker->paragraphs(4, true),
            ]);
        }
    }
}
