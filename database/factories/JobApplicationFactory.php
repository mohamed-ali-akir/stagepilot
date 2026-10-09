<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_siren' => fn () => Company::factory()->create()->siren,
            'status' => ApplicationStatus::PendingReview,
            'priority' => 'A',
            'distance_km' => 3.5,
        ];
    }

    /** Approved by the student, ready to be sent. */
    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => ApplicationStatus::Ready,
            'hook' => 'Vos logiciels de gestion m\'intéressent, car j\'ai contribué pendant mon stage à la migration d\'une application métier vers Laravel.',
            'approved_at' => now()->subDay(),
        ]);
    }

    public function sent(int $daysAgo = 1): static
    {
        return $this->ready()->state(fn () => [
            'status' => ApplicationStatus::Sent,
            'sent_at' => now()->subDays($daysAgo),
        ]);
    }
}
