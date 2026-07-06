<?php

namespace App\Services;

use App\Models\ExamViolation;
use App\Models\ProctoringSession;
use App\Models\CheatingAlert;
use Illuminate\Support\Facades\DB;

class ViolationService
{
    /**
     * Main entry: report violation
     */
    public function report(ProctoringSession $session, array $data): object
    {
        return DB::transaction(function () use ($session, $data) {
            // Lock the session row to prevent race conditions on risk_score and other metrics
            $lockedSession = ProctoringSession::where('id', $session->id)->lockForUpdate()->first();

            if (!$lockedSession) {
                return (object) [
                    'id' => null,
                    'risk_score' => $session->risk_score,
                    'alert_id' => null,
                    'skipped' => true
                ];
            }

            // Cooldown check on backend to prevent DB spam (e.g. from multiple open tabs or repeat alerts)
            $recent = ExamViolation::where('proctoring_session_id', $lockedSession->id)
                ->where('violation_type', $data['violation_type'])
                ->where('timestamp', '>=', now()->subSeconds(60))
                ->first();

            if ($recent) {
                $session->refresh();
                return (object) [
                    'id' => $recent->id,
                    'risk_score' => $lockedSession->risk_score,
                    'alert_id' => null,
                    'skipped' => true
                ];
            }

            // 1. Create violation
            $violation = ExamViolation::create([
                'proctoring_session_id' => $lockedSession->id,
                'student_id' => $lockedSession->student_id,
                'violation_type' => $data['violation_type'],
                'severity' => $data['severity'],
                'description' => $data['description'] ?? null,
                'evidence' => $data['evidence'] ?? [],
                'detected_by' => 'system',
                'timestamp' => now(),
            ]);

            // 2. Increment violations_count + type-specific counter
            $lockedSession->increment('violations_count');
            $this->incrementTypeCounter($lockedSession, $data['violation_type']);

            // 3. Increment risk score
            $newScore = $this->incrementRiskScore($lockedSession, $violation);

            // 4. Create alert
            $alert = $this->createAlert($lockedSession, $violation);

            // Refresh original model to reflect changes in PHP memory for the caller
            $session->refresh();

            return (object) [
                'id' => $violation->id,
                'risk_score' => $newScore,
                'alert_id' => $alert?->id
            ];
        });
    }

    /**
     * Increment the right counter column based on violation type
     */
    private function incrementTypeCounter(ProctoringSession $session, string $type): void
    {
        $column = match ($type) {
            'face_swap', 'multiple_faces', 'face_not_visible' => 'face_detection_alerts',
            'tab_switched', 'browser_opened' => 'tab_switch_alerts',
            'copy_paste' => 'copy_paste_alerts',
            'external_device', 'phone_usage' => 'external_device_alerts',
            default => null,
        };

        if ($column) {
            $session->increment($column);
        }
    }
    /**
     * FAST risk engine (no DB loop)
     */
    private function incrementRiskScore(ProctoringSession $session, ExamViolation $violation): int
    {
        $weight = $this->getViolationWeight($violation->violation_type);
        $multiplier = $this->getSeverityMultiplier($violation->severity);

        $increase = $weight * $multiplier;

        $current = (int) $session->risk_score;

        $newScore = min(100, $current + $increase);

        $session->update([
            'risk_score' => $newScore
        ]);

        return $newScore;
    }

    /**
     * Map violation type → base weight
     */
    private function getViolationWeight(string $type): int
    {
        return match ($type) {
            'face_swap' => 30,
            'multiple_faces' => 25,
            'external_device' => 20,
            'tab_switched' => 10,
            'copy_paste' => 15,
            'suspicious_behavior' => 18,
            'face_not_visible' => 12,
            'phone_usage' => 22,
            default => 10,
        };
    }

    /**
     * Severity multiplier
     */
    private function getSeverityMultiplier(string $severity): float
    {
        return match ($severity) {
            'info' => 0.2,
            'low' => 0.5,
            'medium' => 1.0,
            'high' => 1.5,
            'critical' => 2.0,
            default => 1.0,
        };
    }

    /**
     * Create cheating alert
     */
    private function createAlert(ProctoringSession $session, ExamViolation $violation): ?CheatingAlert
    {
        $severity = $this->mapAlertSeverity($violation->severity);

        return CheatingAlert::create([
            'proctoring_session_id' => $session->id,
            'violation_id' => $violation->id,
            'alert_type' => 'instant',
            'message' => $this->buildMessage($violation),
            'severity' => $severity,
        ]);
    }

    /**
     * Normalize alert severity
     */
    private function mapAlertSeverity(string $severity): string
    {
        return match ($severity) {
            'critical' => 'critical',
            'high' => 'critical',
            'medium' => 'alert',
            'low' => 'warning',
            default => 'info',
        };
    }

    /**
     * Human-readable message
     */
    private function buildMessage(ExamViolation $violation): string
    {
        return match ($violation->violation_type) {
            'face_swap' => 'تم اكتشاف عدم تطابق الوجه مع هوية الطالب',
            'multiple_faces' => 'تم اكتشاف أكثر من شخص أمام الكاميرا',
            'tab_switched' => 'تم الانتقال خارج نافذة الامتحان',
            'copy_paste' => 'تم اكتشاف نسخ أو لصق محتوى',
            'external_device' => 'تم اكتشاف جهاز خارجي',
            'phone_usage' => 'تم اكتشاف استخدام الهاتف',
            default => 'تم تسجيل مخالفة أثناء الامتحان',
        };
    }

    /**
     * Optional: trigger external handling (webhooks / notifications)
     */
    public function triggerAlert(ProctoringSession $session, ExamViolation $violation): void
    {
        // مستقبلًا: WebSocket / Notifications / Admin dashboard
        // حالياً مجرد hook جاهز للتوسع

        \Log::info('Proctoring alert triggered', [
            'session_id' => $session->id,
            'violation' => $violation->violation_type,
            'severity' => $violation->severity,
        ]);
    }
}