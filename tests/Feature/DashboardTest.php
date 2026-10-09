<?php

use App\Jobs\PrepareApplicationsJob;
use App\Livewire\Dashboard;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('redirects guests to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('sends students who did not finish the questionnaire to it', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding'));
});

it('shows the dashboard once the questionnaire is done', function () {
    $this->actingAs(student())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Tableau de bord')
        ->assertSee('Envois automatiques');
});

it('turns sending on and off', function () {
    $user = student(['paused' => true]);

    Livewire::actingAs($user)->test(Dashboard::class)->set('sendingEnabled', true);
    expect($user->profile->refresh()->paused)->toBeFalse();

    Livewire::actingAs($user)->test(Dashboard::class)->set('sendingEnabled', false);
    expect($user->profile->refresh()->paused)->toBeTrue();
});

it('refuses to turn sending on without a Gmail app password', function () {
    $user = student(['paused' => true]);
    $user->forceFill(['gmail_app_password' => null])->save();

    Livewire::actingAs($user)->test(Dashboard::class)->set('sendingEnabled', true)->assertSet('sendingEnabled', false);
    expect($user->profile->refresh()->paused)->toBeTrue();
});

it('queues a preparation in the background', function () {
    Queue::fake();
    $user = student();

    Livewire::actingAs($user)->test(Dashboard::class)->call('prepare');

    Queue::assertPushed(PrepareApplicationsJob::class, fn ($job) => $job->userId === $user->id);
});

it('stops everything when the internship is found', function () {
    $user = student();

    Livewire::actingAs($user)->test(Dashboard::class)->call('internshipFound');

    expect($user->profile->refresh()->internship_found_at)->not->toBeNull()
        ->and($user->profile->paused)->toBeTrue();
});
