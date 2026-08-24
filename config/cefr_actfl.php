<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CEFR & ACTFL Scoring Thresholds
    |--------------------------------------------------------------------------
    |
    | Two skill groups:
    |   - core:       Listening, Reading, Structure  → score% converted to /900 points
    |   - productive: Writing, Speaking              → score% used directly (0–100)
    |
    | Keys are the MINIMUM value to reach that level (descending order matters).
    | To change thresholds, just edit the numbers here — no code change needed.
    |
    */

    'core' => [
        'cefr' => [
            801 => 'C1.2',
            701 => 'C1.1',
            668 => 'B2.2',
            634 => 'B2.1',
            601 => 'B1.2',
            501 => 'B1.1',
            401 => 'A2.2',
            301 => 'A2.1',
            201 => 'A1.2',
            0 => 'A1.1',
        ],
        'actfl' => [
            801 => 'Superior',
            701 => 'Advanced High',
            668 => 'Advanced Mid+',
            634 => 'Advanced Mid',
            601 => 'Advanced Low',
            501 => 'Intermediate High',
            401 => 'Intermediate Mid',
            301 => 'Intermediate Low',
            201 => 'Novice High',
            101 => 'Novice Mid',
            0 => 'Novice Low',
        ],
    ],

    'productive' => [
        'cefr' => [
            900 => 'C2',
            801 => 'C2',
            701 => 'C1.2',
            668 => 'C1.1',
            634 => 'B2.2',
            601 => 'B2.1',
            501 => 'B1.2',
            401 => 'B1.1',
            301 => 'A2.2',
            201 => 'A2.1',
            101 => 'A1.2',
            0 => 'A1.1',
        ],
        'actfl' => [
            900 => 'Superior',
            801 => 'Superior',
            701 => 'Advanced High',
            668 => 'Advanced Mid+',
            634 => 'Advanced Mid',
            601 => 'Advanced Low',
            501 => 'Intermediate High',
            401 => 'Intermediate Mid',
            301 => 'Intermediate Low',
            201 => 'Novice High',
            101 => 'Novice Mid',
            0 => 'Novice Low',
        ],
    ],
];
