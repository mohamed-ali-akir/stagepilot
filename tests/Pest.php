<?php

use App\Models\Experience;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Services\Hooks\HookGenerator;
use App\Services\Hooks\HookProposal;
use App\Services\Mail\Mailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\FakeHookGenerator;
use Tests\Support\FakeMailbox;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * A student who completed the questionnaire: profile, one team experience, one skill, Gmail app password.
 */
function student(array $profile = []): User
{
    $user = User::factory()->create(['gmail_app_password' => 'abcdefghijklmnop']);
    Profile::factory()->for($user)->create($profile);
    Experience::factory()->for($user)->create();
    $skill = Skill::firstOrCreate(['name' => 'Laravel']);
    $user->skills()->attach($skill, ['level' => 'good']);

    return $user->fresh(['profile.program', 'experiences', 'skills']);
}

/** Replaces Gmail by a fake for the whole test. */
function fakeMailbox(): FakeMailbox
{
    $fake = new FakeMailbox;
    app()->instance(Mailbox::class, $fake);

    return $fake;
}

/** Replaces Claude by a fake answer for the whole test. */
function fakeClaude(HookProposal|string|null $answer = null): FakeHookGenerator
{
    $fake = new FakeHookGenerator($answer);
    app()->instance(HookGenerator::class, $fake);

    return $fake;
}

/** A weekday morning far from holidays, so that sending is allowed. */
function onAWeekday(): void
{
    test()->travelTo(Carbon::parse('2026-10-13 09:00'));
}

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
