<?php

namespace Database\Seeders;

use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create('fr_FR');

        // Créer un super admin en premier
        User::create([
            'name' => $faker->lastName(),
            'pseudo' => $faker->unique()->userName(),
            'password' => bcrypt('12345'),
            'role' => 'super_admin',
        ]);

        foreach (range(1, 9) as $index) {
            User::create([
                'name' => $faker->lastName(),
                'pseudo' => $faker->unique()->userName(),
                'password' => bcrypt('12345'),
                'role' => $faker->randomElement(['super_admin', 'staff']),
            ]);
        }
    }
}
