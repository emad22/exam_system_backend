<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Services\CertificateService;
use Tests\TestCase;

class CertificateSystemTest extends TestCase
{
    public function test_it_calculates_overall_score_using_core_skills_only(): void
    {
        $service = new CertificateService();

        $this->assertSame(80.0, $service->calculateCoreOverallScoreFromSkillScores([
            ['score' => 80, 'is_core' => true],
            ['score' => 90, 'is_core' => true],
            ['score' => 70, 'is_core' => true],
            ['score' => 100, 'is_core' => false],
        ]));

        $this->assertSame(85.0, $service->calculateCoreOverallScoreFromSkillScores([
            ['score' => 80, 'is_core' => true],
            ['score' => 90, 'is_core' => true],
        ]));
    }

    public function test_it_uses_selected_template_date_format_for_certificate_dates(): void
    {
        $service = new CertificateService();
        $template = CertificateTemplate::make([
            'background_settings' => ['date_format' => 'd/m/Y'],
        ]);

        $this->assertSame('d/m/Y', $service->getCertificateDateFormat($template));
        $this->assertSame('11/08/2026', $service->formatCertificateDate('2026-08-11', 'd/m/Y'));
    }

    public function test_is_core_skill_identifies_3_core_skills_only(): void
    {
        $service = new CertificateService();

        $listening = (object) ['skill' => (object) ['name' => 'Listening'], 'attempt' => (object) ['exam_id' => 1], 'skill_id' => 1];
        $reading = (object) ['skill' => (object) ['name' => 'Reading Comprehension'], 'attempt' => (object) ['exam_id' => 1], 'skill_id' => 2];
        $structure = (object) ['skill' => (object) ['name' => 'Structure & Grammar'], 'attempt' => (object) ['exam_id' => 1], 'skill_id' => 3];
        $writing = (object) ['skill' => (object) ['name' => 'Writing'], 'attempt' => (object) ['exam_id' => 1], 'skill_id' => 4];
        $speaking = (object) ['skill' => (object) ['name' => 'Live Speaking'], 'attempt' => (object) ['exam_id' => 1], 'skill_id' => 5];

        $this->assertTrue($service->isCoreSkill($listening));
        $this->assertTrue($service->isCoreSkill($reading));
        $this->assertTrue($service->isCoreSkill($structure));
        $this->assertFalse($service->isCoreSkill($writing));
        $this->assertFalse($service->isCoreSkill($speaking));
    }
}
