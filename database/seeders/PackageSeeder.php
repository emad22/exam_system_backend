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
            'name' => 'Adult-Basic',
            'skills_count' => 3,
            'description' => '3 skills package',
            'wp_package_id' => '6542'
        ]);

        Package::create([
            'id' => 2,
            'name' => 'Adult-Standard',
            'skills_count' => 4,
            'description' => '4 skills package',
            'wp_package_id' => '6544'

        ]);

        Package::create([
            'id' => 3,
            'name' => 'Adult-Premium',
            'skills_count' => 5,
            'description' => '5 skills package',
            'wp_package_id' => '6546'
        ]);
    }
}
