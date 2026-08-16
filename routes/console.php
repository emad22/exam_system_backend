<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('exam:recalculate-overall-scores', function () {
    $coreKeywords = ['listen', 'read', 'struct', 'grammar'];
    $coreSkillIds = \App\Models\Skill::where(function ($query) use ($coreKeywords) {
        foreach ($coreKeywords as $word) {
            $query->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($word) . '%']);
        }
    })->pluck('id')->toArray();

    $attempts = \App\Models\ExamAttempt::with('attemptSkills')->get();
    $updatedCount = 0;

    foreach ($attempts as $attempt) {
        $coreScores = $attempt->attemptSkills
            ->whereIn('skill_id', $coreSkillIds)
            ->pluck('score')
            ->filter(fn ($s) => !is_null($s))
            ->map(fn ($s) => (float) $s)
            ->values()
            ->toArray();

        $newOverall = count($coreScores) > 0
            ? round(array_sum($coreScores) / count($coreScores), 2)
            : 0.0;

        if (abs(($attempt->overall_score ?? 0) - $newOverall) > 0.001) {
            $oldScore = $attempt->overall_score;
            $attempt->update(['overall_score' => $newOverall]);
            $this->line("Attempt #{$attempt->id}: updated overall_score from {$oldScore} to {$newOverall}");
            $updatedCount++;
        }
    }

    $this->info("Recalculation complete. Updated {$updatedCount} attempt(s).");
})->purpose('Recalculate and repair overall_score for all exam attempts using core skills only');
