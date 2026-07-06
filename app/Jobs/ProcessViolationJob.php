<?php

namespace App\Jobs;

use App\Models\ProctoringSession;
use App\Services\ViolationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// app/Jobs/ProcessViolationJob.php
class ProcessViolationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $sessionId,
        public array $data
    ) {}

    public function handle(ViolationService $service): void
    {
        $session = ProctoringSession::find($this->sessionId);
        if ($session) {
            $service->report($session, $this->data);
        }
    }
}