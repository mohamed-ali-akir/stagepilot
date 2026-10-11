<?php

use App\Enums\ApplicationStatus;
use App\Livewire\Applications;
use App\Livewire\Review;
use App\Models\JobApplication;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = student();
    $this->hook = 'Vos logiciels de gestion m\'intéressent, car j\'ai contribué pendant mon stage à la migration d\'une application métier vers Laravel.';
});

it('lists hooks to review and approves one', function () {
    $application = JobApplication::factory()->for($this->user)->create(['hook' => $this->hook, 'hook_generated' => true, 'proof_excerpt' => 'logiciels de gestion pour les PME', 'proof_url' => 'https://x.fr']);

    Livewire::actingAs($this->user)->test(Review::class)
        ->assertSee($application->company->name)
        ->assertSee('logiciels de gestion pour les PME')
        ->call('approve', $application->id)
        ->assertHasNoErrors();

    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::Ready)
        ->and($application->approved_at)->not->toBeNull()
        ->and($application->hook_generated)->toBeTrue();
});

it('refuses an edited hook that breaks the rules', function () {
    $application = JobApplication::factory()->for($this->user)->create(['hook' => $this->hook]);

    Livewire::actingAs($this->user)->test(Review::class)
        ->call('edit', $application->id)
        ->set("drafts.{$application->id}", 'Vous êtes le leader du marché !')
        ->call('approve', $application->id)
        ->assertHasErrors("drafts.{$application->id}");

    expect($application->refresh()->status)->toBe(ApplicationStatus::PendingReview);
});

it('lets the student write a hook when the automatic one failed', function () {
    $application = JobApplication::factory()->for($this->user)->create(['hook_error' => 'extrait introuvable']);

    Livewire::actingAs($this->user)->test(Review::class)
        ->set("drafts.{$application->id}", $this->hook)
        ->call('approve', $application->id);

    expect($application->refresh()->hook)->toBe($this->hook)
        ->and($application->hook_generated)->toBeFalse()
        ->and($application->status)->toBe(ApplicationStatus::Ready);
});

it('discards an application', function () {
    $application = JobApplication::factory()->for($this->user)->create(['hook' => $this->hook]);

    Livewire::actingAs($this->user)->test(Review::class)->call('discard', $application->id);

    expect($application->refresh()->status)->toBe(ApplicationStatus::Ignored);
});

it('never shows or touches another student\'s applications', function () {
    $other = JobApplication::factory()->for(student())->create(['hook' => $this->hook]);

    Livewire::actingAs($this->user)->test(Review::class)
        ->assertDontSee($other->company->name)
        ->call('approve', $other->id)
        ->assertStatus(404);

    expect($other->refresh()->status)->toBe(ApplicationStatus::PendingReview);
});

it('filters applications and sets a status after a reply', function () {
    $sent = JobApplication::factory()->sent()->for($this->user)->create();
    $ready = JobApplication::factory()->ready()->for($this->user)->create();

    Livewire::actingAs($this->user)->test(Applications::class)
        ->set('status', 'sent')
        ->assertSee($sent->company->name)
        ->assertDontSee($ready->company->name)
        ->call('setStatus', $sent->id, 'interview');

    expect($sent->refresh()->status)->toBe(ApplicationStatus::Interview);
});

it('blocks the company for everyone on "do not contact"', function () {
    $sent = JobApplication::factory()->sent()->for($this->user)->create();

    Livewire::actingAs($this->user)->test(Applications::class)->call('setStatus', $sent->id, 'do_not_contact');

    expect($sent->company->refresh()->blocked)->toBeTrue();
});

it('refuses statuses the student cannot set by hand', function () {
    $sent = JobApplication::factory()->sent()->for($this->user)->create();

    Livewire::actingAs($this->user)->test(Applications::class)
        ->call('setStatus', $sent->id, 'ready')
        ->assertHasErrors('value');

    expect($sent->refresh()->status)->toBe(ApplicationStatus::Sent);
});

it('shows the reason a hook could not be written without escaping it twice', function () {
    JobApplication::factory()->for($this->user)->create(['hook_error' => "aucune phrase du site exploitable : écris l'accroche toi-même"]);

    $this->actingAs($this->user)->get(route('review'))
        ->assertSee("écris l'accroche toi-même")
        ->assertDontSee('&amp;#039;', false);
});
