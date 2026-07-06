<?php
// app/Jobs/CompareFacesJob.php

namespace App\Jobs;

use App\Models\ProctoringSession;
use App\Services\FaceMonitoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CompareFacesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 45;

    public function __construct(
        public int     $sessionId,
        public ?string $screenshot
    ) {}

    public function handle(FaceMonitoringService $service): void
    {
        $session = ProctoringSession::find($this->sessionId);
        if (!$session) return;

        $service->compareFaces($session, $this->screenshot);
    }
}