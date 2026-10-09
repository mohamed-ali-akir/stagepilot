<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ApplicationSender;
use App\Services\DailyDigest;
use App\Services\ReplyChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('stagepilot:morning {--user= : id of one student only}')]
#[Description('Routine du matin : lit les réponses, programme les envois du jour, envoie le résumé')]
class MorningRoutine extends Command
{
    public function handle(ReplyChecker $replies, ApplicationSender $sender, DailyDigest $digest): int
    {
        $users = User::whereHas('profile')
            ->whereNotNull('gmail_app_password')
            ->when($this->option('user'), fn ($q, $id) => $q->whereKey($id))
            ->with('profile.program')
            ->get();

        foreach ($users as $user) {
            try {
                $found = $replies->check($user);
            } catch (\Throwable $e) {
                // safety first: if replies could not be read, nobody gets a follow-up today
                $this->error("{$user->name} : réponses illisibles ({$e->getMessage()}), aucun envoi");

                continue;
            }

            $blocked = $sender->blockedReason($user);
            $queued = $blocked ? 0 : $sender->scheduleToday($user);
            $this->info("{$user->name} : {$found['replies']} réponse(s), $queued mail(s) programmé(s)".($blocked ? " ($blocked)" : ''));

            try {
                $digest->send($user, $found, $queued, $blocked);
            } catch (\Throwable $e) {
                $this->warn("Résumé non envoyé : {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
