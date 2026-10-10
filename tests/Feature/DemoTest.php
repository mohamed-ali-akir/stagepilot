<?php

use App\Enums\ApplicationStatus;
use App\Enums\SentEmailType;
use App\Livewire\Dashboard;
use App\Livewire\Onboarding;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\ApplicationSender;
use App\Services\DemoAccount;
use App\Services\Mail\GmailMailbox;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->demo = app(DemoAccount::class)->reset();
});

describe('the demo button', function () {
    it('is on the home page for visitors', function () {
        auth()->logout();

        $this->get('/')->assertOk()->assertSee('Essayer la démo');
    });

    it('logs the visitor into the demo account and shows the demo banner', function () {
        auth()->logout();

        $this->post(route('demo'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->demo);
        $this->get(route('dashboard'))->assertOk()->assertSee('Mode démo')->assertSee('Aucun mail');
    });

    it('can be switched off', function () {
        config(['stagepilot.demo_enabled' => false]);

        $this->post(route('demo'))->assertNotFound();
        $this->assertGuest();
    });
});

describe('the demo data', function () {
    it('shows applications at every stage, only with fictional companies', function () {
        $applications = $this->demo->jobApplications()->with('company')->get();

        expect($applications)->toHaveCount(12)
            ->and($applications->pluck('status')->unique()->count())->toBeGreaterThanOrEqual(7)
            ->and($this->demo->profile->paused)->toBeTrue()
            ->and($this->demo->gmail_app_password)->toBeNull();

        foreach ($applications as $application) {
            expect($application->company->siren)->toStartWith('000')
                ->and($application->company->email === null || str_ends_with($application->company->email, '.test'))->toBeTrue();
        }
    });

    it('is rebuilt from scratch by the nightly reset, without touching real students', function () {
        $student = student();
        $real = JobApplication::factory()->for($student)->create();
        $this->demo->jobApplications()->update(['status' => ApplicationStatus::DoNotContact->value]);

        $this->artisan('stagepilot:demo-reset')->assertSuccessful();

        $demo = User::where('is_demo', true)->sole();
        expect($demo->jobApplications()->withStatus(ApplicationStatus::PendingReview)->count())->toBe(3)
            ->and($real->fresh())->not->toBeNull()
            ->and(User::where('is_demo', true)->count())->toBe(1);
    });
});

describe('no email can ever leave the demo account', function () {
    it('cannot send even if a Gmail password got into it', function () {
        $mailbox = fakeMailbox();
        $this->demo->forceFill(['gmail_app_password' => 'abcdefghijklmnop'])->save();
        $this->demo->profile->update(['paused' => false]);
        $ready = $this->demo->jobApplications()->withStatus(ApplicationStatus::Ready)->first();
        onAWeekday();

        $reason = app(ApplicationSender::class)->send($ready, SentEmailType::Application);

        expect($this->demo->fresh()->canSendEmails())->toBeFalse()
            ->and($reason)->toContain('démonstration')
            ->and($mailbox->sent)->toBe([]);
    });

    it('is refused by the Gmail connection itself (last lock)', function () {
        expect(fn () => app(GmailMailbox::class)->send($this->demo, 'x@example.com', 'Sujet', 'Texte', null, '<id@x>'))
            ->toThrow(LogicException::class, 'démonstration');
    });

    it('is skipped by the 8:00 preparation and the 9:00 routine', function () {
        Http::preventStrayRequests();
        $mailbox = fakeMailbox();
        $this->demo->forceFill(['gmail_app_password' => 'abcdefghijklmnop'])->save();

        $this->artisan('stagepilot:prepare', ['--now' => true])->assertSuccessful();
        $this->artisan('stagepilot:morning')->assertSuccessful();

        expect($mailbox->sent)->toBe([])->and($mailbox->drafts)->toBe([]);
    });

    it('refuses the dashboard actions that would search, send or spoil the demo', function () {
        Queue::fake();
        $mailbox = fakeMailbox();

        Livewire::actingAs($this->demo)->test(Dashboard::class)
            ->call('prepare')
            ->call('sendTest')
            ->call('internshipFound')
            ->set('sendingEnabled', true);

        Queue::assertNothingPushed();
        expect($mailbox->sent)->toBe([])
            ->and($this->demo->profile->fresh()->internship_found_at)->toBeNull()
            ->and($this->demo->profile->fresh()->paused)->toBeTrue();
    });
});

describe('the shared demo account cannot be taken over', function () {
    it('cannot open the account settings', function (string $route) {
        $this->actingAs($this->demo)->get(route($route))->assertRedirect(route('dashboard'));
    })->with(['profile.edit', 'security.edit']);

    it('can look at the questionnaire but never save it (nor a Gmail password)', function () {
        Livewire::actingAs($this->demo)->test(Onboarding::class)
            ->set('gmail_app_password', 'abcd efgh ijkl mnop')
            ->call('save');

        expect($this->demo->fresh()->gmail_app_password)->toBeNull();
    });
});
