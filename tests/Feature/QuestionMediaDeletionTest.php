<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Level;
use App\Models\Question;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionMediaDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_clear_question_media_via_update_endpoint()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $skill = Skill::create(['name' => 'Listening', 'short_code' => 'L']);
        $exam = Exam::create(['title' => 'General Exam']);

        $level = Level::firstOrCreate(
            ['skill_id' => $skill->id, 'level_number' => 1],
            [
                'min_score' => 0,
                'max_score' => 100,
                'default_standalone_quantity' => 0,
                'default_passage_quantity' => 0,
                'default_question_count' => 0
            ]
        );

        $question = Question::create([
            'skill_id' => $skill->id,
            'exam_id' => $exam->id,
            'level_id' => $level->id,
            'type' => 'listening',
            'content' => 'What did you hear?',
            'points' => 5,
            'image_path' => 'questions/images/sample.png',
            'audio_path' => 'questions/audio/sample.mp3',
            'media_path' => 'questions/sample.mp4',
        ]);

        $this->assertEquals('questions/images/sample.png', $question->image_path);
        $this->assertEquals('questions/audio/sample.mp3', $question->audio_path);
        $this->assertEquals('questions/sample.mp4', $question->media_path);

        $payload = [
            'skill_id' => $skill->id,
            'exam_id' => $exam->id,
            'level_id' => 1,
            'passage_mode' => 'none',
            'questions' => [
                [
                    'id' => $question->id,
                    'type' => 'listening',
                    'content' => 'What did you hear?',
                    'instructions' => 'Listen carefully.',
                    'points' => 5,
                    'sort_order' => 0,
                    'clear_q_image' => true,
                    'clear_q_audio' => true,
                    'clear_q_media' => true,
                    'options' => [
                        [
                            'option_text' => 'Option A',
                            'is_correct' => true,
                        ],
                        [
                            'option_text' => 'Option B',
                            'is_correct' => false,
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/admin/questions/{$question->id}", $payload);

        $response->assertStatus(200);

        $question->refresh();
        $this->assertNull($question->image_path);
        $this->assertNull($question->audio_path);
        $this->assertNull($question->media_path);
    }
}
