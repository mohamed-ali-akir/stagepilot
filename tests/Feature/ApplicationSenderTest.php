<?php

use App\Enums\ApplicationStatus;
use App\Enums\SentEmailType;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\SentEmail;
use App\Services\ApplicationSender;
use Illuminate\Support\Carbon;

beforeEach(function () {
    onAWeekday();
    $this->mailbox = fakeMailbox();
    $this->user = student();
});

function readyApplications($user, int $count, array $attributes = []): \Illuminate\Support\Collection
{
    return JobApplication::factory()->count($count)->ready()->for($user)->create($attributes);
}

it('sends ready applications and marks them sent', function () {
    $applications = readyApplications($this->user, 3);

    expect(app(ApplicationSender::class)->scheduleToday($this->user))->toBe(3)
        ->and($this->mailbox->recipients())->toHaveCount(3);
    foreach ($applications as $application) {
        expect($application->refresh()->status)->toBe(ApplicationStatus::Sent)
            ->and($application->sentEmails()->count())->toBe(1);
    }
});

it('never sends the same application twice', function () {
    readyApplications($this->user, 2);
    app(ApplicationSender::class)->scheduleToday($this->user);
    app(ApplicationSender::class)->scheduleToday($this->user);

    expect($this->mailbox->recipients())->toHaveCount(2);
});

it('writes the hook, the CV and a unique Message-ID in each email', function () {
    $application = readyApplications($this->user, 1)->first();
    $this->user->profile->update(['cv_path' => 'cvs/cv-1.pdf']);

    app(ApplicationSender::class)->send($application, SentEmailType::Application);

    $email = $this->mailbox->sent[0];
    expect($email['body'])->toContain($application->hook)
        ->and($email['body'])->toContain($this->user->profile->phone)
        ->and($email['body'])->not->toContain('{')
        ->and($email['attachment'])->toEndWith('cv-1.pdf')
        ->and(SentEmail::first()->message_id)->toBe($email['messageId']);
});

it('respects the daily quota over several runs', function () {
    $this->user->profile->update(['daily_quota' => 5]);
    readyApplications($this->user, 12);

    app(ApplicationSender::class)->scheduleToday($this->user);
    app(ApplicationSender::class)->scheduleToday($this->user);

    expect($this->mailbox->recipients())->toHaveCount(5);
});

it('caps the quota at 20 even if the profile says more', function () {
    $this->user->profile->update(['daily_quota' => 50]);

    expect(app(ApplicationSender::class)->remainingQuota($this->user->fresh('profile')))->toBe(20);
});

it('sends nothing when paused, on weekends, on holidays or once the internship is found', function (Closure $setup) {
    readyApplications($this->user, 2);
    $setup($this->user);

    expect(app(ApplicationSender::class)->scheduleToday($this->user->fresh('profile')))->toBe(0)
        ->and($this->mailbox->sent)->toBe([]);
})->with([
    'paused' => [fn ($u) => $u->profile->update(['paused' => true])],
    'weekend' => [fn ($u) => test()->travelTo(Carbon::parse('2026-10-17 09:00'))],
    'holiday' => [fn ($u) => test()->travelTo(Carbon::parse('2026-11-11 09:00'))],
    'internship found' => [fn ($u) => JobApplication::factory()->for($u)->create(['status' => ApplicationStatus::InternshipSecured])],
    'no Gmail password' => [fn ($u) => $u->forceFill(['gmail_app_password' => null])->save()],
    'too close to the internship' => [fn ($u) => $u->profile->update(['internship_start' => now()->addDays(10)])],
]);

it('re-checks the hook right before sending', function () {
    readyApplications($this->user, 1, ['hook' => 'Vous êtes le leader incontournable du secteur et je rêve d\'y travailler un jour.']);

    expect(app(ApplicationSender::class)->scheduleToday($this->user))->toBe(0);
});

it('waits one night before sending an automatic hook', function () {
    $application = readyApplications($this->user, 1, ['approved_at' => null])->first();

    expect(app(ApplicationSender::class)->scheduleToday($this->user))->toBe(0);

    $this->travelTo(Carbon::parse('2026-10-14 09:00'));
    expect(app(ApplicationSender::class)->scheduleToday($this->user))->toBe(1)
        ->and($application->refresh()->status)->toBe(ApplicationStatus::Sent);
});

it('limits each company to 2 applications per month for all students', function () {
    $company = Company::factory()->create();
    foreach ([student(), student()] as $other) {
        JobApplication::factory()->sent(3)->for($other)->create(['company_siren' => $company->siren]);
    }
    readyApplications($this->user, 1, ['company_siren' => $company->siren]);

    expect(app(ApplicationSender::class)->scheduleToday($this->user))->toBe(0);
});

it('sends one follow-up after 7 days, then never again', function () {
    $old = JobApplication::factory()->sent(7)->for($this->user)->create();
    JobApplication::factory()->sent(6)->for($this->user)->create();

    app(ApplicationSender::class)->scheduleToday($this->user);
    expect($this->mailbox->sent)->toHaveCount(1)
        ->and($this->mailbox->sent[0]['subject'])->toStartWith('Re:')
        ->and($this->mailbox->sent[0]['body'])->toContain($old->sent_at->format('d/m/Y'))
        ->and($old->refresh()->status)->toBe(ApplicationStatus::FollowedUp);

    $this->travelTo(Carbon::parse('2026-10-20 09:00'));
    app(ApplicationSender::class)->scheduleToday($this->user);
    expect(SentEmail::where('job_application_id', $old->id)->count())->toBe(1, 'one follow-up only');
});

it('keeps the application ready and notes the error when Gmail refuses', function () {
    $application = readyApplications($this->user, 1)->first();
    $this->mailbox->refused = [$application->company->email];

    expect(app(ApplicationSender::class)->send($application, SentEmailType::Application))->toContain('erreur Gmail')
        ->and($application->refresh()->status)->toBe(ApplicationStatus::Ready)
        ->and($application->notes)->toContain('envoi refusé');
});
