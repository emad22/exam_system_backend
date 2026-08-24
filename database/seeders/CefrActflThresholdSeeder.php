<?php

namespace Database\Seeders;

use App\Models\CefrActflThreshold;
use Illuminate\Database\Seeder;

class CefrActflThresholdSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [];

        // ── CORE (Listening / Reading / Structure) ──────────────────────────
        // CEFR — min_score is on /900 scale
        $coreCefr = [
            ['min_score' => 801, 'level_label' => 'C1.2'],
            ['min_score' => 701, 'level_label' => 'C1.1'],
            ['min_score' => 668, 'level_label' => 'B2.2'],
            ['min_score' => 634, 'level_label' => 'B2.1'],
            ['min_score' => 601, 'level_label' => 'B1.2'],
            ['min_score' => 501, 'level_label' => 'B1.1'],
            ['min_score' => 401, 'level_label' => 'A2.2'],
            ['min_score' => 301, 'level_label' => 'A2.1'],
            ['min_score' => 201, 'level_label' => 'A1.2'],
            ['min_score' =>   0, 'level_label' => 'A1.1'],
        ];

        // ACTFL — min_score is on /900 scale
        $coreActfl = [
            ['min_score' => 801, 'level_label' => 'Superior'],
            ['min_score' => 701, 'level_label' => 'Advanced High'],
            ['min_score' => 668, 'level_label' => 'Advanced Mid+'],
            ['min_score' => 634, 'level_label' => 'Advanced Mid'],
            ['min_score' => 601, 'level_label' => 'Advanced Low'],
            ['min_score' => 501, 'level_label' => 'Intermediate High'],
            ['min_score' => 401, 'level_label' => 'Intermediate Mid'],
            ['min_score' => 301, 'level_label' => 'Intermediate Low'],
            ['min_score' => 201, 'level_label' => 'Novice High'],
            ['min_score' => 101, 'level_label' => 'Novice Mid'],
            ['min_score' =>   0, 'level_label' => 'Novice Low'],
        ];

        // ── PRODUCTIVE (Writing / Speaking) ─────────────────────────────────
        // CEFR — min_score is on /900 scale
        $productiveCefr = [
            ['min_score' => 801, 'level_label' => 'C2'],
            ['min_score' => 701, 'level_label' => 'C1.2'],
            ['min_score' => 668, 'level_label' => 'C1.1'],
            ['min_score' => 634, 'level_label' => 'B2.2'],
            ['min_score' => 601, 'level_label' => 'B2.1'],
            ['min_score' => 501, 'level_label' => 'B1.2'],
            ['min_score' => 401, 'level_label' => 'B1.1'],
            ['min_score' => 301, 'level_label' => 'A2.2'],
            ['min_score' => 201, 'level_label' => 'A2.1'],
            ['min_score' => 101, 'level_label' => 'A1.2'],
            ['min_score' =>   0, 'level_label' => 'A1.1'],
        ];

        // ACTFL — min_score is on /900 scale
        $productiveActfl = [
            ['min_score' => 801, 'level_label' => 'Superior'],
            ['min_score' => 701, 'level_label' => 'Advanced High'],
            ['min_score' => 668, 'level_label' => 'Advanced Mid+'],
            ['min_score' => 634, 'level_label' => 'Advanced Mid'],
            ['min_score' => 601, 'level_label' => 'Advanced Low'],
            ['min_score' => 501, 'level_label' => 'Intermediate High'],
            ['min_score' => 401, 'level_label' => 'Intermediate Mid'],
            ['min_score' => 301, 'level_label' => 'Intermediate Low'],
            ['min_score' => 201, 'level_label' => 'Novice High'],
            ['min_score' => 101, 'level_label' => 'Novice Mid'],
            ['min_score' =>   0, 'level_label' => 'Novice Low'],
        ];

        $order = 0;
        foreach ($coreCefr as $row) {
            $rows[] = array_merge($row, ['skill_group' => 'core', 'framework' => 'cefr', 'sort_order' => $order++]);
        }
        $order = 0;
        foreach ($coreActfl as $row) {
            $rows[] = array_merge($row, ['skill_group' => 'core', 'framework' => 'actfl', 'sort_order' => $order++]);
        }
        $order = 0;
        foreach ($productiveCefr as $row) {
            $rows[] = array_merge($row, ['skill_group' => 'productive', 'framework' => 'cefr', 'sort_order' => $order++]);
        }
        $order = 0;
        foreach ($productiveActfl as $row) {
            $rows[] = array_merge($row, ['skill_group' => 'productive', 'framework' => 'actfl', 'sort_order' => $order++]);
        }

        foreach ($rows as $row) {
            CefrActflThreshold::updateOrCreate(
                [
                    'skill_group' => $row['skill_group'],
                    'framework'   => $row['framework'],
                    'min_score'   => $row['min_score'],
                ],
                [
                    'level_label' => $row['level_label'],
                    'sort_order'  => $row['sort_order'],
                    'is_active'   => true,
                ]
            );
        }
    }
}
