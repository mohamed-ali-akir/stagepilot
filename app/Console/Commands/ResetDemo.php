<?php

namespace App\Console\Commands;

use App\Services\DemoAccount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('stagepilot:demo-reset')]
#[Description('Remet le compte de démonstration à zéro (données fictives, aucun mail)')]
class ResetDemo extends Command
{
    public function handle(DemoAccount $demo): int
    {
        $user = $demo->reset();
        $this->info("Compte démo prêt : {$user->jobApplications()->count()} candidatures fictives");

        return self::SUCCESS;
    }
}
