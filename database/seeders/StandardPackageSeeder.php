<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\Skill;
use Illuminate\Database\Seeder;

class StandardPackageSeeder extends Seeder
{
    public function run(): void
    {
        $skills = [
            'listening' => Skill::where('name', 'Listening')->first(),
            'reading' => Skill::where('name', 'Reading')->first(),
            'structure' => Skill::where('name', 'Structure')->first(),
            'writing' => Skill::where('name', 'Writing')->first(),
            'speaking' => Skill::where('name', 'Speaking')->first(),
        ];

        // 1. Adult Starter (3 Skills)
        Package::updateOrCreate(
            ['name' => 'Adult Starter (3 Skills)'],
            [
                'skills_count' => 3,
                'description' => 'Core assessment: Listening, Reading, Structure.',
                'wp_package_id' => 'pkg_adult_3',
                'exam_id'=> '1',
            ]
        );

        // 2. Adult Plus (4 Skills)
        Package::updateOrCreate(
            ['name' => 'Adult Plus (4 Skills)'],
            [
                'skills_count' => 4,
                'description' => 'Enhanced assessment: Listening, Reading, Structure, Writing.',
                'wp_package_id' => 'pkg_adult_4',
                'exam_id'=> '1',
            ]
        );

        // 3. Adult Elite (5 Skills)
        Package::updateOrCreate(
            ['name' => 'Adult Elite (5 Skills)'],
            [
                'skills_count' => 5,
                'description' => 'Full assessment: Listening, Reading, Structure, Writing, Speaking.',
                'wp_package_id' => 'pkg_adult_5',
                'exam_id'=> '1',
            ]
        );
    }
}
