<?php

namespace App\Jobs;

use App\Enums\SentEmailType;
use App\Models\JobApplication;
use App\Services\ApplicationSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one application or follow-up. Queued with a delay so emails are spaced out.
 */
class SendApplicationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $applicationId, public SentEmailType $type) {}

    public function handle(ApplicationSender $sender): void
    {
        $application = JobApplication::find($this->applicationId);
        if ($application) {
            $sender->send($application, $this->type);
        }
    }
}
