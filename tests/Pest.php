<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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
function student(array $profile = []): \App\Models\User
{
    $user = \App\Models\User::factory()->create(['gmail_app_password' => 'abcdefghijklmnop']);
    \App\Models\Profile::factory()->for($user)->create($profile);
    \App\Models\Experience::factory()->for($user)->create();
    $skill = \App\Models\Skill::firstOrCreate(['name' => 'Laravel']);
    $user->skills()->attach($skill, ['level' => 'good']);

    return $user->fresh(['profile.program', 'experiences', 'skills']);
}

/** Replaces Gmail by a fake for the whole test. */
function fakeMailbox(): \Tests\Support\FakeMailbox
{
    $fake = new \Tests\Support\FakeMailbox;
    app()->instance(\App\Services\Mail\Mailbox::class, $fake);

    return $fake;
}

/** Replaces Claude by a fake answer for the whole test. */
function fakeClaude(\App\Services\Hooks\HookProposal|string|null $answer = null): \Tests\Support\FakeHookGenerator
{
    $fake = new \Tests\Support\FakeHookGenerator($answer);
    app()->instance(\App\Services\Hooks\HookGenerator::class, $fake);

    return $fake;
}

/** A weekday morning far from holidays, so that sending is allowed. */
function onAWeekday(): void
{
    test()->travelTo(\Illuminate\Support\Carbon::parse('2026-10-13 09:00'));
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
