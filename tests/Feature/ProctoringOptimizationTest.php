<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Student;
use App\Models\ProctoringSession;
use App\Models\FaceDetectionLog;
use App\Models\ExamViolation;
use App\Models\ExamAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProctoringOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_face_log_rate_limiting_cooldown()
    {
        $user = User::factory()->create(['role' => 'student']);

        $category = \App\Models\ExamCategory::create([
            'name' => 'Adults',
            'slug' => 'adults',
            'is_active' => true
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'exam_category_id' => $category->id,
        ]);

        $session = ProctoringSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'session_token' => 'test-token-xyz-123',
        ]);

        // Make first face-log request
        $response1 = $this->actingAs($user)->postJson("/api/v1/proctoring/session/{$session->id}/face-log", [
            'face_count' => 0,
            'screenshot' => null,
        ]);

        $response1->assertStatus(200);
        $response1->assertJsonFragment(['logged' => true]);

        $this->assertEquals(1, FaceDetectionLog::where('proctoring_session_id', $session->id)->count());

        // Make second face-log request immediately (should be skipped due to cooldown)
        $response2 = $this->actingAs($user)->postJson("/api/v1/proctoring/session/{$session->id}/face-log", [
            'face_count' => 0,
            'screenshot' => null,
        ]);

        $response2->assertStatus(200);
        $response2->assertJsonFragment(['logged' => false]);

        $this->assertEquals(1, FaceDetectionLog::where('proctoring_session_id', $session->id)->count());
    }

    public function test_violation_reporting_cooldown()
    {
        $user = User::factory()->create(['role' => 'student']);
        $category = \App\Models\ExamCategory::create([
            'name' => 'Adults',
            'slug' => 'adults',
            'is_active' => true
        ]);
        $student = Student::create([
            'user_id' => $user->id,
            'exam_category_id' => $category->id,
        ]);
        $session = ProctoringSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'session_token' => 'test-token-xyz-123',
        ]);

        // Report first violation via endpoint
        $response1 = $this->actingAs($user)->postJson("/api/v1/proctoring/session/{$session->id}/violation", [
            'violation_type' => 'tab_switched',
            'severity' => 'medium',
            'description' => 'Tab switched',
        ]);

        $response1->assertStatus(200);
        $this->assertEquals(1, ExamViolation::where('proctoring_session_id', $session->id)->where('violation_type', 'tab_switched')->count());

        // Report second identical violation immediately (should be skipped due to cooldown)
        $response2 = $this->actingAs($user)->postJson("/api/v1/proctoring/session/{$session->id}/violation", [
            'violation_type' => 'tab_switched',
            'severity' => 'medium',
            'description' => 'Tab switched again',
        ]);

        $response2->assertStatus(200);
        $this->assertEquals(1, ExamViolation::where('proctoring_session_id', $session->id)->where('violation_type', 'tab_switched')->count());

        // Travel 61 seconds into the future
        $this->travel(61)->seconds();

        // Report third identical violation after 61 seconds (should be allowed now)
        $response3 = $this->actingAs($user)->postJson("/api/v1/proctoring/session/{$session->id}/violation", [
            'violation_type' => 'tab_switched',
            'severity' => 'medium',
            'description' => 'Tab switched yet again',
        ]);

        $response3->assertStatus(200);
        $this->assertEquals(2, ExamViolation::where('proctoring_session_id', $session->id)->where('violation_type', 'tab_switched')->count());
    }

    public function test_login_closes_active_proctoring_sessions()
    {
        $user = User::factory()->create([
            'role' => 'student',
            'password' => bcrypt('password123'),
            'is_active' => true
        ]);

        $category = \App\Models\ExamCategory::create([
            'name' => 'Adults',
            'slug' => 'adults',
            'is_active' => true
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'exam_category_id' => $category->id,
        ]);

        $session = ProctoringSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'session_token' => 'test-token-active-123',
            'started_at' => now()->subMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'login' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        $session->refresh();
        $this->assertEquals('ended', $session->status);
        $this->assertEquals('completed', $session->recording_status);
        $this->assertEquals('session_replaced', $session->close_reason);
        $this->assertNotNull($session->ended_at);
        $this->assertNotNull($session->closed_at);
        $this->assertGreaterThanOrEqual(600, $session->duration_seconds);
    }

    public function test_logout_closes_paused_proctoring_sessions()
    {
        $user = User::factory()->create([
            'role' => 'student',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $category = \App\Models\ExamCategory::create([
            'name' => 'Adults',
            'slug' => 'adults',
            'is_active' => true,
        ]);

        $student = Student::create([
            'user_id' => $user->id,
            'exam_category_id' => $category->id,
        ]);

        $session = ProctoringSession::create([
            'student_id' => $student->id,
            'status' => 'paused',
            'session_token' => 'test-token-paused-123',
            'started_at' => now()->subMinutes(10),
            'paused_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/logout');

        $response->assertStatus(200);

        $session->refresh();
        $this->assertEquals('ended', $session->status);
        $this->assertEquals('student_left', $session->close_reason);
        $this->assertNotNull($session->ended_at);
        $this->assertNotNull($session->closed_at);
    }

    public function test_new_exam_attempt_creates_new_proctoring_session()
    {
        $user = User::factory()->create(['role' => 'student']);
        $category = \App\Models\ExamCategory::create([
            'name' => 'Adults',
            'slug' => 'adults',
            'is_active' => true,
        ]);
        $student = Student::create([
            'user_id' => $user->id,
            'exam_category_id' => $category->id,
        ]);
        $exam = \App\Models\Exam::create([
            'title' => 'General Exam',
            'exam_type' => 'adult',
        ]);

        $existingSession = ProctoringSession::create([
            'student_id' => $student->id,
            'status' => 'pending',
            'session_token' => 'pre-exam-session',
        ]);

        $attempt = ExamAttempt::create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/proctoring/session/initiate', [
            'exam_attempt_id' => $attempt->id,
        ]);

        $response->assertStatus(200);
        $this->assertCount(2, ProctoringSession::where('student_id', $student->id)->get());
        $this->assertNotEquals($existingSession->id, $response->json('session_id'));
    }

    public function test_ended_session_id_is_not_reused_for_new_attempt()
    {
        $user = User::factory()->create(['role' => 'student']);
        $category = \App\Models\ExamCategory::create([
            'name' => 'Adults',
            'slug' => 'adults',
            'is_active' => true,
        ]);
        $student = Student::create([
            'user_id' => $user->id,
            'exam_category_id' => $category->id,
        ]);
        $exam = \App\Models\Exam::create([
            'title' => 'General Exam',
            'exam_type' => 'adult',
        ]);

        $endedSession = ProctoringSession::create([
            'student_id' => $student->id,
            'status' => 'ended',
            'session_token' => 'ended-session-token',
            'closed_at' => now(),
            'ended_at' => now(),
        ]);

        $attempt = ExamAttempt::create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/proctoring/session/initiate', [
            'exam_attempt_id' => $attempt->id,
            'session_id' => $endedSession->id,
        ]);

        $response->assertStatus(200);
        $this->assertNotEquals($endedSession->id, $response->json('session_id'));
        $this->assertEquals('pending', $response->json('status'));
    }

    public function test_ended_session_for_same_attempt_is_not_reused()
    {
        $user = User::factory()->create(['role' => 'student']);
        $category = \App\Models\ExamCategory::create([
            'name' => 'Adults',
            'slug' => 'adults',
            'is_active' => true,
        ]);
        $student = Student::create([
            'user_id' => $user->id,
            'exam_category_id' => $category->id,
        ]);
        $exam = \App\Models\Exam::create([
            'title' => 'General Exam',
            'exam_type' => 'adult',
        ]);

        $attempt = ExamAttempt::create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'in_progress',
        ]);

        ProctoringSession::create([
            'student_id' => $student->id,
            'exam_attempt_id' => $attempt->id,
            'status' => 'ended',
            'session_token' => 'ended-for-same-attempt',
            'closed_at' => now(),
            'ended_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/proctoring/session/initiate', [
            'exam_attempt_id' => $attempt->id,
        ]);

        $response->assertStatus(200);
        $this->assertNotEquals('ended', $response->json('status'));
        $this->assertEquals(2, ProctoringSession::where('student_id', $student->id)->count());
    }

    public function test_identity_verification_creates_new_session_when_previous_is_ended()
    {
        $user = User::factory()->create(['role' => 'student']);
        $category = \App\Models\ExamCategory::create([
            'name' => 'Adults',
            'slug' => 'adults',
            'is_active' => true,
        ]);
        $student = Student::create([
            'user_id' => $user->id,
            'exam_category_id' => $category->id,
            'student_code' => '123456789',
        ]);

        $endedSession = ProctoringSession::create([
            'student_id' => $student->id,
            'status' => 'ended',
            'session_token' => 'ended-identity-session',
            'closed_at' => now(),
            'ended_at' => now(),
        ]);

        $result = app(\App\Services\IdentityVerificationService::class)->verify($user, [
            'id_number' => '123456789',
            'face_image' => 'dummy-image',
            'id_image' => null,
        ]);

        $this->assertTrue($result->verified);
        $this->assertNotEquals($endedSession->id, $result->session_id);
        $this->assertEquals(2, ProctoringSession::where('student_id', $student->id)->count());
    }

    public function test_can_fetch_face_image_route_securely()
    {
        $user = User::factory()->create(['role' => 'student']);
        $category = \App\Models\ExamCategory::create([
            'name' => 'Adults',
            'slug' => 'adults',
            'is_active' => true
        ]);
        $student = Student::create([
            'user_id' => $user->id,
            'exam_category_id' => $category->id,
        ]);

        // Place a dummy file in the public disk
        \Illuminate\Support\Facades\Storage::fake('public');
        $fileName = 'proctoring/faces/7_face_1782806728.jpg';
        \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, 'dummy-image-content');
        $faceImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($fileName);

        $session = ProctoringSession::create([
            'student_id' => $student->id,
            'status' => 'active',
            'session_token' => 'test-token-xyz-123',
            'device_info' => [
                'face_image' => $faceImageUrl,
            ],
        ]);

        // 1. Unauthenticated request should fail
        $responseUnauth = $this->getJson("/api/v1/proctoring/session/{$session->id}/face-image");
        $responseUnauth->assertStatus(401);

        // 2. Authenticated student of the session should succeed
        $responseAuth = $this->actingAs($user)->getJson("/api/v1/proctoring/session/{$session->id}/face-image");
        $responseAuth->assertStatus(200);
        $this->assertEquals('dummy-image-content', $responseAuth->getContent());

        // 3. Different student should be forbidden (403)
        $anotherUser = User::factory()->create(['role' => 'student']);
        $anotherStudent = Student::create([
            'user_id' => $anotherUser->id,
            'exam_category_id' => $category->id,
        ]);
        $responseForbidden = $this->actingAs($anotherUser)->getJson("/api/v1/proctoring/session/{$session->id}/face-image");
        $responseForbidden->assertStatus(403);
    }

    public function test_initiate_session_handles_multiple_sessions_properly()
    {
        $user = User::factory()->create(['role' => 'student']);
        $category = \App\Models\ExamCategory::create([
            'name' => 'Adults',
            'slug' => 'adults',
            'is_active' => true
        ]);
        $student = Student::create([
            'user_id' => $user->id,
            'exam_category_id' => $category->id,
        ]);

        $exam = \App\Models\Exam::create([
            'title' => 'General Exam',
            'exam_type' => 'adult',
        ]);

        $attempt = ExamAttempt::create([
            'student_id' => $student->id,
            'exam_id' => $exam->id,
            'status' => 'in_progress',
        ]);

        // 1. Create an ended session for this attempt
        $endedSession = ProctoringSession::create([
            'student_id' => $student->id,
            'exam_attempt_id' => $attempt->id,
            'status' => 'ended',
            'session_token' => 'token-ended-111',
        ]);

        // 2. Create an active session with NULL attempt (simulating identity verification)
        $activeSession = ProctoringSession::create([
            'student_id' => $student->id,
            'exam_attempt_id' => null,
            'status' => 'pending',
            'session_token' => 'token-active-222',
        ]);

        // Call initiateSession API passing both session_id and exam_attempt_id
        $response = $this->actingAs($user)->postJson('/api/v1/proctoring/session/initiate', [
            'exam_attempt_id' => $attempt->id,
            'session_id' => $activeSession->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'success' => true,
            'session_id' => $activeSession->id,
            'status' => 'pending',
        ]);

        // Check it updated the active session's exam_attempt_id
        $activeSession->refresh();
        $this->assertEquals($attempt->id, $activeSession->exam_attempt_id);

        // Call initiateSession again WITHOUT session_id. It should resolve to the pending one, NOT the ended one.
        $response2 = $this->actingAs($user)->postJson('/api/v1/proctoring/session/initiate', [
            'exam_attempt_id' => $attempt->id,
        ]);

        $response2->assertStatus(200);
        $response2->assertJsonFragment([
            'success' => true,
            'session_id' => $activeSession->id,
            'status' => 'pending',
        ]);
    }
}
