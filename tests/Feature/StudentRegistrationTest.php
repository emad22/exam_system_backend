<?php

namespace Tests\Feature;

use App\Models\ExamCategory;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a default exam category required by student creation
        ExamCategory::create([
            'name' => 'Adults',
            'slug' => 'adults',
            'is_active' => true
        ]);
    }

    public function test_admin_can_create_student_with_manual_student_code()
    {
        $admin = User::factory()->create(['role' => 'admin', 'username' => 'admin_user']);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/students', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'username' => 'johndoe',
                'email' => 'john@example.com',
                'password' => 'password123',
                'student_code' => 'CUSTOM-ID-123',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('students', [
            'student_code' => 'CUSTOM-ID-123',
        ]);
    }

    public function test_admin_can_create_student_without_student_code_retains_null()
    {
        $admin = User::factory()->create(['role' => 'admin', 'username' => 'admin_user']);

        // Test with empty string
        $response1 = $this->actingAs($admin)
            ->postJson('/api/v1/admin/students', [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'username' => 'janedoe',
                'email' => 'jane@example.com',
                'password' => 'password123',
                'student_code' => '',
            ]);

        $response1->assertStatus(201);
        
        $student1 = Student::whereHas('user', function($q) {
            $q->where('username', 'janedoe');
        })->first();
        $this->assertNull($student1->student_code);

        // Test with missing field
        $response2 = $this->actingAs($admin)
            ->postJson('/api/v1/admin/students', [
                'first_name' => 'Jack',
                'last_name' => 'Doe',
                'username' => 'jackdoe',
                'email' => 'jack@example.com',
                'password' => 'password123',
            ]);

        $response2->assertStatus(201);
        
        $student2 = Student::whereHas('user', function($q) {
            $q->where('username', 'jackdoe');
        })->first();
        $this->assertNull($student2->student_code);
    }

    public function test_student_code_uniqueness_allows_multiple_nulls_but_prevents_duplicate_codes()
    {
        $admin = User::factory()->create(['role' => 'admin', 'username' => 'admin_user']);

        // First student with null code
        $this->actingAs($admin)->postJson('/api/v1/admin/students', [
            'first_name' => 'Student',
            'last_name' => 'One',
            'username' => 'stu1',
            'email' => 'stu1@example.com',
            'password' => 'password123',
            'student_code' => '',
        ])->assertStatus(201);

        // Second student with null code -> Should also succeed
        $this->actingAs($admin)->postJson('/api/v1/admin/students', [
            'first_name' => 'Student',
            'last_name' => 'Two',
            'username' => 'stu2',
            'email' => 'stu2@example.com',
            'password' => 'password123',
            'student_code' => null,
        ])->assertStatus(201);

        // Third student with code XYZ
        $this->actingAs($admin)->postJson('/api/v1/admin/students', [
            'first_name' => 'Student',
            'last_name' => 'Three',
            'username' => 'stu3',
            'email' => 'stu3@example.com',
            'password' => 'password123',
            'student_code' => 'XYZ-999',
        ])->assertStatus(201);

        // Fourth student with same code XYZ -> Should fail validation
        $response = $this->actingAs($admin)->postJson('/api/v1/admin/students', [
            'first_name' => 'Student',
            'last_name' => 'Four',
            'username' => 'stu4',
            'email' => 'stu4@example.com',
            'password' => 'password123',
            'student_code' => 'XYZ-999',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('student_code');
    }

    public function test_verify_identity_enforces_matching_registered_student_code()
    {
        // Create user & student profile with registered code
        $user = User::factory()->create(['role' => 'student', 'username' => 'exam_student']);
        $student = Student::create([
            'user_id' => $user->id,
            'student_code' => 'STU-ABC-123',
            'exam_category_id' => ExamCategory::first()->id,
        ]);

        // 1. Send verifyIdentity with wrong ID number -> should fail database check
        $response1 = $this->actingAs($user)->postJson('/api/v1/proctoring/verify-identity', [
            'face_image' => 'data:image/jpeg;base64,abc',
            'id_image' => 'data:image/jpeg;base64,def',
            'id_number' => 'STU-WRONG',
        ]);

        $response1->assertStatus(422);
        $response1->assertJsonFragment([
            'verified' => false,
            'message' => 'رقم الهوية المدخل لا يتطابق مع كود الطالب المسجل لدينا.'
        ]);

        // 2. Send verifyIdentity with correct ID number -> should pass database check (and try to hit Gemini API)
        // Since Gemini API key might not be configured, it'll bypass or succeed
        $response2 = $this->actingAs($user)->postJson('/api/v1/proctoring/verify-identity', [
            'face_image' => 'data:image/jpeg;base64,abc',
            'id_image' => 'data:image/jpeg;base64,def',
            'id_number' => 'STU-ABC-123',
        ]);

        // It should either return 200 (verified) or fail because of actual image data, but NOT because of the database mismatch
        $this->assertNotEquals(422, $response2->status());
    }

    public function test_verify_identity_allows_any_id_number_if_no_student_code_registered()
    {
        // Create user & student profile with no student code
        $user = User::factory()->create(['role' => 'student', 'username' => 'uncoded_student']);
        $student = Student::create([
            'user_id' => $user->id,
            'student_code' => null,
            'exam_category_id' => ExamCategory::first()->id,
        ]);

        // Send verifyIdentity with any ID number -> database check should be skipped, and it should proceed to Gemini AI validation
        $response = $this->actingAs($user)->postJson('/api/v1/proctoring/verify-identity', [
            'face_image' => 'data:image/jpeg;base64,abc',
            'id_image' => 'data:image/jpeg;base64,def',
            'id_number' => 'ANY-PASSPORT-456',
        ]);

        // It should not return 422 mismatch
        $this->assertNotEquals(422, $response->status());
    }
}
