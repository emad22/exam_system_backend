<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Student;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamCategory;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateSystemTest extends TestCase
{
    use RefreshDatabase;

    private $studentUser;
    private $student;
    private $partnerUser;
    private $partner;
    private $admin;
    private $exam;
    private $attempt;
    private $template;

    protected function setUp(): void
    {
        parent::setUp();

        $category = ExamCategory::create([
            'name' => 'Adults',
            'slug' => 'adults',
            'is_active' => true
        ]);

        $this->partner = Partner::create([
            'name' => 'Test Partner',
            'slug' => 'test-partner',
            'is_active' => true
        ]);

        $this->partnerUser = User::factory()->create([
            'role' => 'partner',
            'is_active' => true
        ]);
        $this->partnerUser->partner()->save($this->partner);

        $this->studentUser = User::factory()->create([
            'role' => 'student',
            'is_active' => true
        ]);

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'exam_category_id' => $category->id,
            'partner_id' => $this->partner->id,
            'student_code' => 'STU-112233'
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true
        ]);

        $this->exam = Exam::create([
            'title' => 'General English Exam',
            'exam_type' => 'adult',
            'is_active' => true
        ]);

        $this->template = CertificateTemplate::create([
            'name' => 'Default Certificate',
            'content_html' => '<h1>Certificate of Completion</h1><p>{name}</p>',
            'is_default' => true
        ]);

        $this->attempt = ExamAttempt::create([
            'student_id' => $this->student->id,
            'user_id' => $this->studentUser->id,
            'exam_id' => $this->exam->id,
            'status' => 'completed',
            'overall_score' => 85.5
        ]);
    }

    public function test_student_only_sees_visible_certificates()
    {
        // 1. Create a non-visible certificate
        $cert = Certificate::create([
            'student_id' => $this->student->id,
            'exam_attempt_id' => $this->attempt->id,
            'template_id' => $this->template->id,
            'certificate_number' => 'CERT-100',
            'score' => 85.5,
            'issue_date' => now(),
            'verification_code' => 'VERIFY100',
            'is_visible_to_student' => false
        ]);

        // Student lists certificates
        $response = $this->actingAs($this->studentUser)->getJson('/api/v1/student/certificates');
        $response->assertStatus(200);
        $response->assertJsonCount(0);

        // 2. Toggle to true
        $cert->update(['is_visible_to_student' => true]);

        $response = $this->actingAs($this->studentUser)->getJson('/api/v1/student/certificates');
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['certificate_number' => 'CERT-100']);
    }

    public function test_student_cannot_download_non_visible_certificate()
    {
        $cert = Certificate::create([
            'student_id' => $this->student->id,
            'exam_attempt_id' => $this->attempt->id,
            'template_id' => $this->template->id,
            'certificate_number' => 'CERT-200',
            'score' => 85.5,
            'issue_date' => now(),
            'verification_code' => 'VERIFY200',
            'is_visible_to_student' => false
        ]);

        // Student tries to download
        $response = $this->actingAs($this->studentUser)->getJson("/api/v1/student/certificates/{$cert->id}/download");
        $response->assertStatus(403);

        // Toggle to true
        $cert->update(['is_visible_to_student' => true]);

        Storage::fake('public');
        Storage::disk('public')->put($cert->file_path ?? 'certificates/CERT-200.pdf', 'dummy pdf contents');
        $cert->update(['file_path' => 'certificates/CERT-200.pdf']);

        $response = $this->actingAs($this->studentUser)->getJson("/api/v1/student/certificates/{$cert->id}/download");
        $response->assertStatus(200);
    }

    public function test_partner_can_see_and_download_their_students_certificates()
    {
        $cert = Certificate::create([
            'student_id' => $this->student->id,
            'exam_attempt_id' => $this->attempt->id,
            'template_id' => $this->template->id,
            'certificate_number' => 'CERT-300',
            'score' => 85.5,
            'issue_date' => now(),
            'verification_code' => 'VERIFY300',
            'is_visible_to_student' => false // false for student, but partner should still see/download
        ]);

        // Partner list certificates
        $response = $this->actingAs($this->partnerUser)->getJson('/api/v1/partner/certificates');
        $response->assertStatus(200);
        $response->assertJsonFragment(['certificate_number' => 'CERT-300']);

        // Partner download
        Storage::fake('public');
        Storage::disk('public')->put('certificates/CERT-300.pdf', 'dummy pdf contents');
        $cert->update(['file_path' => 'certificates/CERT-300.pdf']);

        $response = $this->actingAs($this->partnerUser)->getJson("/api/v1/student/certificates/{$cert->id}/download");
        $response->assertStatus(200);

        // Another partner should not see or download it
        $otherPartnerUser = User::factory()->create(['role' => 'partner', 'is_active' => true]);
        $otherPartner = Partner::create(['name' => 'Other Partner', 'slug' => 'other-partner', 'is_active' => true]);
        $otherPartnerUser->partner()->save($otherPartner);

        $responseListOther = $this->actingAs($otherPartnerUser)->getJson('/api/v1/partner/certificates');
        $responseListOther->assertStatus(200);
        $responseListOther->assertJsonMissing(['certificate_number' => 'CERT-300']);

        $responseDownloadOther = $this->actingAs($otherPartnerUser)->getJson("/api/v1/student/certificates/{$cert->id}/download");
        $responseDownloadOther->assertStatus(403);
    }

    public function test_admin_can_toggle_visibility()
    {
        $cert = Certificate::create([
            'student_id' => $this->student->id,
            'exam_attempt_id' => $this->attempt->id,
            'template_id' => $this->template->id,
            'certificate_number' => 'CERT-400',
            'score' => 85.5,
            'issue_date' => now(),
            'verification_code' => 'VERIFY400',
            'is_visible_to_student' => false
        ]);

        $response = $this->actingAs($this->admin)->patchJson("/api/v1/admin/certificates/{$cert->id}/toggle-visibility");
        $response->assertStatus(200);
        $response->assertJsonFragment(['is_visible_to_student' => true]);

        $this->assertTrue($cert->fresh()->is_visible_to_student);
    }

    public function test_public_verify_gated_by_visibility()
    {
        $cert = Certificate::create([
            'student_id' => $this->student->id,
            'exam_attempt_id' => $this->attempt->id,
            'template_id' => $this->template->id,
            'certificate_number' => 'CERT-500',
            'score' => 85.5,
            'issue_date' => now(),
            'verification_code' => 'VERIFY500',
            'is_visible_to_student' => false
        ]);

        // Unauthenticated public request -> Should be 404 (or not found/visible) since it is false
        $response = $this->getJson("/api/v1/verify-certificate/VERIFY500");
        $response->assertStatus(404);

        // Make visible
        $cert->update(['is_visible_to_student' => true]);

        $response = $this->getJson("/api/v1/verify-certificate/VERIFY500");
        $response->assertStatus(200);
        $response->assertJsonFragment(['certificate_number' => 'CERT-500']);
    }
}
