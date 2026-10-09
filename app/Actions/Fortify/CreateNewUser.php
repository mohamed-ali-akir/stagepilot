<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->after(function ($validator) use ($input) {
            // Online, anyone could sign up and send emails: only invited addresses may (empty list = open, for local dev)
            $invited = config('stagepilot.invited_emails');
            if ($invited && ! in_array(mb_strtolower(trim($input['email'] ?? '')), $invited, true)) {
                $validator->errors()->add('email', 'Inscription sur invitation : demande à l\'administrateur d\'ajouter ton adresse.');
            }
        })->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // the administrator already vouched for this address by inviting it: no confirmation email needed
        if (config('stagepilot.invited_emails')) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }
}
