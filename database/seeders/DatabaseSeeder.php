<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            LanguageSeeder::class,
            SkillSeeder::class,
            LevelSeeder::class,
            PackageSeeder::class,
            PartnerSeeder::class,
           
        ]);

        User::factory()->create([

            'email' => 'admin@arabacademy.com',
            'role' => 'admin',
        ]);
    }
}
