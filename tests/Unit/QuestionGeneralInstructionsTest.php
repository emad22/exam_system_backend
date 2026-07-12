<?php

namespace Tests\Unit;

use App\Models\Question;
use PHPUnit\Framework\TestCase;

class QuestionGeneralInstructionsTest extends TestCase
{
    public function test_question_model_accepts_general_instructions(): void
    {
        $question = new Question();

        $question->fill([
            'general_instructions' => 'After the audio ends, 5 questions will follow.'
        ]);

        $this->assertSame('After the audio ends, 5 questions will follow.', $question->general_instructions);
    }
}
