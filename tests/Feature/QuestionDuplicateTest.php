<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Level;
use App\Models\Passage;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_duplicate_standalone_question()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $skill = Skill::create(['name' => 'Reading', 'short_code' => 'R']);
        $exam = Exam::create(['title' => 'General Exam']);
        
        // Use firstOrCreate to mimic levels
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
            'type' => 'mcq',
            'content' => 'What is 2+2?',
            'points' => 5,
        ]);

        $option1 = QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => '4',
            'is_correct' => true,
            'sort_order' => 10,
        ]);

        $option2 = QuestionOption::create([
            'question_id' => $question->id,
            'option_text' => '5',
            'is_correct' => false,
            'sort_order' => 20,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/admin/questions/{$question->id}/duplicate");

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'question' => [
                'id',
                'content',
                'options'
            ]
        ]);

        $newQuestionId = $response->json('question.id');
        $this->assertNotEquals($question->id, $newQuestionId);

        $this->assertDatabaseHas('questions', [
            'id' => $newQuestionId,
            'content' => 'What is 2+2?',
            'passage_id' => null,
        ]);

        $this->assertDatabaseHas('question_options', [
            'question_id' => $newQuestionId,
            'option_text' => '4',
            'is_correct' => true,
        ]);
    }

    public function test_can_duplicate_passage_question_and_clones_all_questions_and_passage()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $skill = Skill::create(['name' => 'Reading', 'short_code' => 'R']);
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

        $passage = Passage::create([
            'title' => 'The Solar System',
            'content' => 'Passage content here.',
            'type' => 'text',
        ]);

        $q1 = Question::create([
            'skill_id' => $skill->id,
            'exam_id' => $exam->id,
            'level_id' => $level->id,
            'passage_id' => $passage->id,
            'type' => 'mcq',
            'content' => 'Question 1 content',
            'points' => 2,
        ]);

        $q2 = Question::create([
            'skill_id' => $skill->id,
            'exam_id' => $exam->id,
            'level_id' => $level->id,
            'passage_id' => $passage->id,
            'type' => 'true_false',
            'content' => 'Question 2 content',
            'points' => 3,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/admin/questions/{$q1->id}/duplicate");

        $response->assertStatus(201);
        
        $newQ1Id = $response->json('question.id');
        $this->assertNotEquals($q1->id, $newQ1Id);

        $newQ1 = Question::find($newQ1Id);
        $this->assertNotNull($newQ1->passage_id);
        $this->assertNotEquals($passage->id, $newQ1->passage_id);

        $newPassage = Passage::find($newQ1->passage_id);
        $this->assertEquals('The Solar System', $newPassage->title);

        // Assert that a copy of q2 was also created and linked to the new passage
        $newQ2 = Question::where('passage_id', $newPassage->id)
            ->where('id', '!=', $newQ1Id)
            ->first();

        $this->assertNotNull($newQ2);
        $this->assertEquals('Question 2 content', $newQ2->content);
        $this->assertEquals('true_false', $newQ2->type);
    }

    public function test_deleting_passage_question_deletes_passage_and_all_associated_questions()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $skill = Skill::create(['name' => 'Reading', 'short_code' => 'R']);
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

        $passage = Passage::create([
            'title' => 'The Solar System',
            'content' => 'Passage content here.',
            'type' => 'text',
        ]);

        $q1 = Question::create([
            'skill_id' => $skill->id,
            'exam_id' => $exam->id,
            'level_id' => $level->id,
            'passage_id' => $passage->id,
            'type' => 'mcq',
            'content' => 'Question 1 content',
            'points' => 2,
        ]);

        $q2 = Question::create([
            'skill_id' => $skill->id,
            'exam_id' => $exam->id,
            'level_id' => $level->id,
            'passage_id' => $passage->id,
            'type' => 'true_false',
            'content' => 'Question 2 content',
            'points' => 3,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/admin/questions/{$q1->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('passages', ['id' => $passage->id]);
        $this->assertDatabaseMissing('questions', ['id' => $q1->id]);
        $this->assertDatabaseMissing('questions', ['id' => $q2->id]);
    }
}
