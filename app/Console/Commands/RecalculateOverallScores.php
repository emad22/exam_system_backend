<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExamAttempt;
use App\Models\Skill;

class RecalculateOverallScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exam:recalculate-overall-scores';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate and repair overall_score for all exam attempts using core skills only';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting recalculation of overall scores for all exam attempts...');

        $coreKeywords = ['listen', 'read', 'struct', 'grammar'];
        $coreSkillIds = Skill::where(function ($query) use ($coreKeywords) {
            foreach ($coreKeywords as $word) {
                $query->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($word) . '%']);
            }
        })->pluck('id')->toArray();

        $attempts = ExamAttempt::with(['attemptSkills', 'certificate'])->get();
        $updatedCount = 0;
        $updatedCertCount = 0;

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

            if ($attempt->certificate) {
                if (abs(($attempt->certificate->score ?? 0) - $newOverall) > 0.001) {
                    $oldCertScore = $attempt->certificate->score;
                    $attempt->certificate->update(['score' => $newOverall]);
                    $this->line("Certificate #{$attempt->certificate->id}: updated score from {$oldCertScore} to {$newOverall}");
                    $updatedCertCount++;
                }
            }
        }

        $this->info("Recalculation complete. Updated {$updatedCount} attempt(s) and {$updatedCertCount} certificate(s).");
        return 0;
    }
}
