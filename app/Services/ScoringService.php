<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ScoringService
{
    /**
     * Grade a single answer and return whether it is correct.
     *
     * @param  Question  $question  Eager-loaded with 'options'
     * @param  array     $answerData  One element from $request->answers
     */
    public function gradeAnswer(Question $question, array $answerData): bool
    {
        return match ($question->type) {
            'mcq'                          => $this->gradeMcq($question, $answerData),
            'true_false'                   => $this->gradeMcq($question, $answerData),
            'drag_drop'                    => $this->gradeDragDrop($question, $answerData),
            'word_selection', 'click_word' => $this->gradeWordSelection($question, $answerData),
            'fill_blank'                   => $this->gradeFillBlank($question, $answerData),
            'matching'                     => $this->gradeMatching($question, $answerData),
            'ordering'                     => $this->gradeOrdering($question, $answerData),
            'highlight'                    => $this->gradeHighlight($question, $answerData),
            default                        => $this->gradeText($question, $answerData),
        };
    }

    /**
     * Serialize a student's raw answer into a string suitable for DB storage.
     */
    public function serializeAnswerForStorage(Question $question, array $answerData): ?string
    {
        return match ($question->type) {
            'word_selection', 'click_word' => json_encode($answerData['selected_words'] ?? []),
            'drag_drop'                    => json_encode($answerData['drag_drop_answers'] ?? []),
            'fill_blank'                   => json_encode($answerData['fill_blank_answers'] ?? []),
            'matching'                     => is_string($answerData['matching_answers'] ?? null)
                                                ? $answerData['matching_answers']
                                                : json_encode($answerData['matching_answers'] ?? []),
            'ordering'                     => json_encode($answerData['ordering_answers'] ?? []),
            'highlight'                    => json_encode($answerData['highlight_answers'] ?? []),
            default                        => $answerData['text_answer'] ?? null,
        };
    }

    /**
     * Store an uploaded audio file for speaking questions and return the path.
     */
    public function storeAudioFile(Request $request, string $attemptId, int $answerIndex): ?string
    {
        if (!$request->hasFile("answers.{$answerIndex}.audio_file")) {
            return null;
        }

        return $request
            ->file("answers.{$answerIndex}.audio_file")
            ->store("attempts/{$attemptId}/answers", 'public');
    }

    // ─── Private per-type graders ────────────────────────────────────────────

    private function gradeMcq(Question $question, array $ans): bool
    {
        if (!isset($ans['option_id'])) {
            return false;
        }

        $option = $question->options->firstWhere('id', (int) $ans['option_id']);
        return $option ? (bool) $option->is_correct : false;
    }

    private function gradeDragDrop(Question $question, array $ans): bool
    {
        // Priority 1: structured 'drag_drop_answers' array
        if (!empty($ans['drag_drop_answers'])) {
            $studentAnswers = $ans['drag_drop_answers'];
        } elseif (isset($ans['text_answer'])) {
            // Legacy: JSON-encoded string
            $studentAnswers = json_decode($ans['text_answer'], true);
        } else {
            return false;
        }

        if (!is_array($studentAnswers)) {
            return false;
        }

        $correctOptions = $question->options()
            ->where('is_correct', true)
            ->orderBy('id', 'asc')
            ->pluck('option_text')
            ->toArray();

        if (count($studentAnswers) !== count($correctOptions)) {
            return false;
        }

        foreach ($studentAnswers as $i => $val) {
            if (trim(strtolower((string) $val)) !== trim(strtolower($correctOptions[$i] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    private function gradeWordSelection(Question $question, array $ans): bool
    {
        $studentSelected = $ans['selected_words'] ?? [];
        if (is_string($studentSelected)) {
            $studentSelected = json_decode($studentSelected, true) ?? [];
        }
        if (!is_array($studentSelected)) {
            return false;
        }

        $correctOptions   = $question->options()->where('is_correct', true)->pluck('option_text')->toArray();
        $incorrectOptions = $question->options()->where('is_correct', false)->pluck('option_text')->toArray();

        if (count($studentSelected) !== count($correctOptions)) {
            return false;
        }

        foreach ($correctOptions as $correct) {
            if (!in_array($correct, $studentSelected)) {
                return false;
            }
        }

        foreach ($studentSelected as $selected) {
            if (in_array($selected, $incorrectOptions)) {
                return false;
            }
        }

        return true;
    }

    private function gradeFillBlank(Question $question, array $ans): bool
    {
        $studentAnswers = $ans['fill_blank_answers'] ?? [];
        $correctOptions = $question->options()->orderBy('id', 'asc')->pluck('option_text')->toArray();

        if (count($studentAnswers) < count($correctOptions)) {
            return false;
        }

        foreach ($correctOptions as $i => $correctVal) {
            if (trim(strtolower($studentAnswers[$i] ?? '')) !== trim(strtolower($correctVal))) {
                return false;
            }
        }

        return true;
    }

    private function gradeMatching(Question $question, array $ans): bool
    {
        $studentMatches = $ans['matching_answers'] ?? [];
        if (is_string($studentMatches)) {
            $studentMatches = json_decode($studentMatches, true) ?? [];
        }
        if (!is_array($studentMatches)) {
            return false;
        }

        $options   = $question->options;
        $pairCount = 0;

        foreach ($options as $opt) {
            if (!str_contains($opt->option_text, '|')) {
                continue;
            }

            $pairCount++;
            $parts          = explode('|', $opt->option_text, 2);
            $expectedTarget = trim($parts[1] ?? '');
            $actualTarget   = $studentMatches[$opt->id] ?? null;

            if (trim((string) $actualTarget) !== $expectedTarget) {
                return false;
            }
        }

        return count($studentMatches) === $pairCount;
    }

    private function gradeOrdering(Question $question, array $ans): bool
    {
        $studentOrder = $ans['ordering_answers'] ?? [];
        $correctOrder = $question->options()->orderBy('id', 'asc')->pluck('option_text')->toArray();

        if (count($studentOrder) !== count($correctOrder)) {
            return false;
        }

        foreach ($correctOrder as $i => $correctVal) {
            if (trim($studentOrder[$i] ?? '') !== trim($correctVal)) {
                return false;
            }
        }

        return true;
    }

    private function gradeHighlight(Question $question, array $ans): bool
    {
        // Same logic as word_selection but reads from 'highlight_answers'
        $studentSelected = $ans['highlight_answers'] ?? [];
        if (is_string($studentSelected)) {
            $studentSelected = json_decode($studentSelected, true) ?? [];
        }
        if (!is_array($studentSelected)) {
            return false;
        }

        $correctOptions   = $question->options()->where('is_correct', true)->pluck('option_text')->toArray();
        $incorrectOptions = $question->options()->where('is_correct', false)->pluck('option_text')->toArray();

        if (count($studentSelected) !== count($correctOptions)) {
            return false;
        }

        foreach ($correctOptions as $correct) {
            if (!in_array($correct, $studentSelected)) {
                return false;
            }
        }

        foreach ($studentSelected as $selected) {
            if (in_array($selected, $incorrectOptions)) {
                return false;
            }
        }

        return true;
    }

    private function gradeText(Question $question, array $ans): bool
    {
        if (!isset($ans['text_answer'])) {
            return false;
        }

        $correctText = $question->options()->where('is_correct', true)->value('option_text');
        return trim(strtolower($ans['text_answer'])) === trim(strtolower((string) $correctText));
    }
}
