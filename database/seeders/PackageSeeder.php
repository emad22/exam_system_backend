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
        // 1. Package with 3 Skills
        Package::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Institutional Core (3 Skills)',
                'skills_count' => 3,
                'description' => 'Listening, Reading, and Grammar (Structure).',
                'skills' => ['LIST', 'READ', 'GRAM'],
                'wp_package_id' => 'pkg_3_skills'
            ]
        );

        // 2. Package with 4 Skills
        Package::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Institutional Plus (4 Skills)',
                'skills_count' => 4,
                'description' => 'Listening, Reading, Grammar, and Writing.',
                'skills' => ['LIST', 'READ', 'GRAM', 'WRIT'],
                'wp_package_id' => 'pkg_4_skills'
            ]
        );

        // 3. Package with 5 Skills
        Package::updateOrCreate(
            ['id' => 3],
            [
                'name' => 'Institutional Elite (5 Skills)',
                'skills_count' => 5,
                'description' => 'Listening, Reading, Grammar, Writing, and Speaking.',
                'skills' => ['LIST', 'READ', 'GRAM', 'WRIT', 'SPEK'],
                'wp_package_id' => 'pkg_5_skills'
            ]
        );

        // 4. Custom Package
        Package::updateOrCreate(
            ['id' => 4],
            [
                'name' => 'Custom Assessment',
                'skills_count' => 0,
                'description' => 'Flexible selection of 1 or 2 specific skills.',
                'skills' => [],
                'wp_package_id' => 'pkg_custom'
            ]
        );
    }
}
