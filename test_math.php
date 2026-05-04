<?php
$correct = ['العربية', 'لغات', 'العالم'];
$student = ['العربية', 'لغات', 'العالم'];
$earned = 0;
$pointsPerBlank = 1/3;
foreach ($student as $i => $val) {
    if (isset($correct[$i]) && trim(strtolower((string) $val)) === trim(strtolower($correct[$i]))) {
        $earned += $pointsPerBlank;
    }
}
echo "Earned: " . $earned;
