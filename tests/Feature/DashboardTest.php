<?php

use App\Jobs\PrepareApplicationsJob;
use App\Livewire\Dashboard;
use App\Models\ActivityLog;
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

it('queues a preparation of the number of hooks chosen', function () {
    Queue::fake();
    $user = student();

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSet('prepareChoices', [5, 10, 20])
        ->call('prepare', 10)
        ->assertSee('Préparation en cours');

    Queue::assertPushed(PrepareApplicationsJob::class, fn ($job) => $job->userId === $user->id && $job->wanted === 10);
});

it('does not start a second preparation while one is running', function () {
    Queue::fake();
    $user = student();

    Livewire::actingAs($user)->test(Dashboard::class)->call('prepare', 5)->call('prepare', 5);

    Queue::assertPushed(PrepareApplicationsJob::class, 1);
});

it('offers the button again once the preparation is finished', function () {
    $user = student();
    ActivityLog::record($user, 'prepare_requested', '5 accroche(s) demandée(s)');
    ActivityLog::record($user, 'prepare', '2 accroche(s) prête(s)');

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertDontSee('Préparation en cours')
        ->assertSee('Préparer des candidatures');
});

it('never asks for more hooks than what is left today', function () {
    Queue::fake();
    $user = student();
    foreach (range(1, 13) as $i) {
        ActivityLog::record($user, 'hook_written', 'accroche');
    }

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSet('prepareChoices', [5, 7])
        ->call('prepare', 20);

    Queue::assertPushed(PrepareApplicationsJob::class, fn ($job) => $job->wanted === 7);
});

it('refuses to prepare once the daily limit is reached', function () {
    Queue::fake();
    $user = student();
    foreach (range(1, 20) as $i) {
        ActivityLog::record($user, 'hook_written', 'accroche');
    }

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('Limite du jour atteinte')
        ->call('prepare', 5);

    Queue::assertNothingPushed();
});

it('stops everything when the internship is found', function () {
    $user = student();

    Livewire::actingAs($user)->test(Dashboard::class)->call('internshipFound');

    expect($user->profile->refresh()->internship_found_at)->not->toBeNull()
        ->and($user->profile->paused)->toBeTrue();
});
