<?php

namespace Database\Seeders;

use App\Models\SystemRequirement;
use Illuminate\Database\Seeder;

class SystemRequirementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $requirements = [
            [
                'title' => 'Stable Internet Connection',
                'description' => 'Ensure a minimum speed of 2Mbps avoid disconnections during the session.',
                'category' => 'Internet',
                'is_active' => true,
                'is_mandatory' => true,
                'order' => 1
            ],
            [
                'title' => 'Desktop Browser (Chrome/Edge)',
                'description' => 'For the best experience, use the latest version of Google Chrome or Microsoft Edge.',
                'category' => 'Browser',
                'is_active' => true,
                'is_mandatory' => true,
                'order' => 2
            ],
            [
                'title' => 'Quiet Environment',
                'description' => 'Please reside in a silent room to maintain focus and academic integrity.',
                'category' => 'General',
                'is_active' => true,
                'is_mandatory' => false,
                'order' => 3
            ],
            [
                'title' => 'Working Audio Output',
                'description' => 'Ensure your speakers or headphones are functional for the Listening section.',
                'category' => 'Hardware',
                'is_active' => true,
                'is_mandatory' => true,
                'order' => 4
            ],
        ];

        foreach ($requirements as $req) {
            SystemRequirement::updateOrCreate(['title' => $req['title']], $req);
        }
    }
}
