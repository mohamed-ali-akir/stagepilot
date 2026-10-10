<?php

namespace App\Console\Commands;

use App\Jobs\PrepareApplicationsJob;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('stagepilot:prepare {--user= : id of one student only} {--now : run now instead of queueing}')]
#[Description('Prépare les prochaines candidatures (sites, emails, accroches vérifiées) sans rien envoyer')]
class PrepareApplications extends Command
{
    public function handle(): int
    {
        // the demo account is never prepared: no calls to the public APIs nor to the AI
        $users = User::where('is_demo', false)
            ->whereHas('profile', fn ($q) => $q->whereNull('internship_found_at'))
            ->when($this->option('user'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($users as $user) {
            if ($this->option('now')) {
                PrepareApplicationsJob::dispatchSync($user->id);
            } else {
                PrepareApplicationsJob::dispatch($user->id);
            }
            $this->info("Préparation lancée pour {$user->name}");
        }

        return self::SUCCESS;
    }
}
