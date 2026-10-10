<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('stagepilot:api-token {email : the student\'s email} {--name=api : a name to recognise the token}')]
#[Description('Crée un jeton d\'API personnel (Sanctum) pour un étudiant ; il n\'est affiché qu\'une fois')]
class CreateApiToken extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user || $user->is_demo) {
            $this->error('Aucun étudiant avec cet email (le compte démo n\'a pas de jeton).');

            return self::FAILURE;
        }

        $token = $user->createToken($this->option('name'))->plainTextToken;
        $this->line('Jeton (à garder secret, il ne sera plus affiché) :');
        $this->line($token);

        return self::SUCCESS;
    }
}
