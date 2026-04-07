<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Package::create([
            'id' => 1,
            'name' => 'Basic',
            'skills_count' => 3,
            'description' => '3 skills package'
        ]);

        Package::create([
            'id' => 2,
            'name' => 'Standard',
            'skills_count' => 4,
            'description' => '4 skills package'
        ]);

        Package::create([
            'id' => 3,
            'name' => 'Premium',
            'skills_count' => 5,
            'description' => '5 skills package'
        ]);
    }
}
