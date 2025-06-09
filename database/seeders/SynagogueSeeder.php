<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Synagogue;
use Faker\Factory as Faker;

class SynagogueSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('fr_FR');

        for ($i = 0; $i < 3; $i++) {
            Synagogue::create([
                'nom' => 'Synagogue ' . $faker->city,
                'localisation' => $faker->address,
                'horaires' => "Lun-Ven : 08h - 18h\nSamedi : 09h - 13h",
            ]);
        }
    }
}
