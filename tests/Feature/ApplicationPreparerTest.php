<?php

use App\Enums\ApplicationStatus;
use App\Enums\ValidationMode;
use App\Models\Company;
use App\Models\JobApplication;
use App\Services\ApplicationPreparer;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeHookGenerator;

beforeEach(function () {
    $this->user = student();
    $this->company = Company::factory()->create([
        'name' => 'Kiwo', 'website' => 'https://kiwo.fr/', 'email' => 'service.client@kiwo.fr',
        'website_searched' => true, 'email_searched' => true,
    ]);
    $this->application = JobApplication::factory()->for($this->user)->create(['company_siren' => $this->company->siren]);
    Http::fake([
        'kiwo.fr*' => Http::response('<html><body><p>Nos logiciels sont développés en interne pour les EHPAD et les SSIAD, de la facturation au dossier de soins.</p></body></html>'),
        '*' => Http::response('', 404),
    ]);
    $this->hook = 'Vos logiciels pour les EHPAD et les SSIAD m\'intéressent, car j\'aimerais travailler sur des applications de gestion utilisées au quotidien.';
});

it('keeps a verified hook for manual review', function () {
    fakeClaude(FakeHookGenerator::proposal($this->hook, 'développés en interne pour les EHPAD et les SSIAD', 'https://kiwo.fr/'));

    expect(app(ApplicationPreparer::class)->prepareOne($this->user, $this->application))->toBe('prepared');
    $this->application->refresh();
    expect($this->application->status)->toBe(ApplicationStatus::PendingReview)
        ->and($this->application->hook)->toBe($this->hook)
        ->and($this->application->proof_url)->toBe('https://kiwo.fr/')
        ->and($this->application->approved_at)->toBeNull();
});

it('makes the hook ready directly in automatic mode (sent no earlier than tomorrow)', function () {
    $this->user->profile->update(['validation_mode' => ValidationMode::AutoWithVeto]);
    fakeClaude(FakeHookGenerator::proposal($this->hook, 'développés en interne pour les EHPAD et les SSIAD', 'https://kiwo.fr/'));

    app(ApplicationPreparer::class)->prepareOne($this->user->fresh('profile'), $this->application);

    expect($this->application->refresh()->status)->toBe(ApplicationStatus::Ready);
});

it('refuses a hook whose proof is not on the site', function () {
    fakeClaude(FakeHookGenerator::proposal($this->hook, 'leader européen des logiciels pour les hôpitaux', 'https://kiwo.fr/'));

    expect(app(ApplicationPreparer::class)->prepareOne($this->user, $this->application))->toBe('failed');
    $this->application->refresh();
    expect($this->application->hook)->toBeNull()
        ->and($this->application->hook_error)->toContain('inventée');
});

it('discards companies Claude finds unrelated', function () {
    fakeClaude(FakeHookGenerator::proposal('', '', '', relevant: false, reason: 'imprimerie, pas de numérique'));

    expect(app(ApplicationPreparer::class)->prepareOne($this->user, $this->application))->toBe('ignored');
    expect($this->application->refresh()->status)->toBe(ApplicationStatus::Ignored)
        ->and($this->application->notes)->toContain('imprimerie');
});

it('discards companies without email and does not call Claude', function () {
    $this->company->update(['email' => null]);
    $claude = fakeClaude();

    expect(app(ApplicationPreparer::class)->prepareOne($this->user, $this->application->fresh()))->toBe('ignored')
        ->and($claude->calls)->toBe(0)
        ->and($this->application->refresh()->notes)->toContain('à appeler');
});

it('stores the Claude error so the student can write the hook', function () {
    fakeClaude('erreur API Claude : RateLimitError');

    app(ApplicationPreparer::class)->prepareOne($this->user, $this->application);

    expect($this->application->refresh()->hook_error)->toContain('RateLimitError');
});

it('keeps trying companies until it has the number of hooks asked', function () {
    fakeClaude(FakeHookGenerator::proposal($this->hook, 'développés en interne pour les EHPAD et les SSIAD', 'https://kiwo.fr/'));
    // three companies without email come first: they are discarded and do not count
    foreach (range(1, 3) as $i) {
        $company = Company::factory()->create(['website' => 'https://kiwo.fr/', 'email' => null, 'website_searched' => true, 'email_searched' => true]);
        JobApplication::factory()->for($this->user)->create(['company_siren' => $company->siren, 'priority' => 0]);
    }
    $this->application->update(['priority' => 5]);

    $stats = app(ApplicationPreparer::class)->prepare($this->user, 1);

    expect($stats)->toMatchArray(['prepared' => 1, 'ignored' => 3])
        ->and($this->application->refresh()->hook)->toBe($this->hook);
});

it('stops at the daily limit of hooks', function () {
    config(['stagepilot.prepare_daily_limit' => 2]);
    fakeClaude(FakeHookGenerator::proposal($this->hook, 'développés en interne pour les EHPAD et les SSIAD', 'https://kiwo.fr/'));
    foreach (range(1, 3) as $i) {
        $company = Company::factory()->create(['website' => 'https://kiwo.fr/', 'email' => "contact{$i}@kiwo.fr", 'website_searched' => true, 'email_searched' => true]);
        JobApplication::factory()->for($this->user)->create(['company_siren' => $company->siren]);
    }

    expect(app(ApplicationPreparer::class)->prepare($this->user, 20)['prepared'])->toBe(2)
        ->and(ApplicationPreparer::remainingToday($this->user))->toBe(0)
        ->and(app(ApplicationPreparer::class)->prepare($this->user, 5)['prepared'])->toBe(0);
});

it('gives up after trying the maximum number of companies', function () {
    config(['stagepilot.prepare_max_companies' => 2]);
    $claude = fakeClaude();
    $this->company->update(['email' => null]);
    foreach (range(1, 3) as $i) {
        $company = Company::factory()->create(['website' => 'https://kiwo.fr/', 'email' => null, 'website_searched' => true, 'email_searched' => true]);
        JobApplication::factory()->for($this->user)->create(['company_siren' => $company->siren]);
    }

    expect(app(ApplicationPreparer::class)->prepare($this->user, 5))->toMatchArray(['prepared' => 0, 'ignored' => 2])
        ->and($claude->calls)->toBe(0);
});
