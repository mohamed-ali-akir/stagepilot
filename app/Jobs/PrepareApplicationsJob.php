<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ApplicationPreparer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Finds companies, websites, emails and writes verified hooks for one student. Never sends anything.
 */
class PrepareApplicationsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $userId, public ?int $wanted = null) {}

    /** Only one preparation at a time per student. */
    public function uniqueId(): string
    {
        return 'prepare-'.$this->userId;
    }

    public function handle(ApplicationPreparer $preparer): void
    {
        // never for the demo account: its companies are fictional and it must not use the APIs or the AI
        if ($user = User::with('profile')->where('is_demo', false)->find($this->userId)) {
            $preparer->prepare($user, $this->wanted);
        }
    }
}
