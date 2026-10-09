<?php

use App\Enums\ApplicationStatus;
use App\Enums\ReplyType;
use App\Enums\SentEmailType;
use App\Models\Company;
use App\Models\JobApplication;
use App\Services\Mail\IncomingMessage;
use App\Services\ReplyChecker;
use Illuminate\Support\Carbon;

beforeEach(function () {
    onAWeekday();
    $this->mailbox = fakeMailbox();
    $this->user = student();
    $make = function (string $name, string $email, ApplicationStatus $status = ApplicationStatus::Sent) {
        $company = Company::factory()->create(['name' => $name, 'email' => $email]);
        $application = JobApplication::factory()->sent(3)->for($this->user)->create(['company_siren' => $company->siren, 'status' => $status]);
        $application->sentEmails()->create(['type' => SentEmailType::Application, 'recipient' => $email, 'message_id' => "<$name@gmail.com>", 'sent_at' => now()->subDays(3)]);

        return $application;
    };
    $this->kiwo = $make('Kiwo', 'service.client@kiwo.fr');
    $this->divioseo = $make('Divioseo', 'info@divioseo.fr', ApplicationStatus::FollowedUp);
    $this->spmi = $make('Spmi', 'info@spmi.fr');
    $this->haby = $make('Haby', 'sebastien.aubry@sadeveloppement.com');
    $this->refused = $make('Refus', 'x@refus.fr', ApplicationStatus::Negative);
    $this->yesterday = Carbon::now()->subDay();
});

it('recognises replies, automatic replies and bounces', function () {
    $this->mailbox->inbox = [
        new IncomingMessage('marie.durand@kiwo.fr', 'Re: Candidature stage', $this->yesterday, '<r1@kiwo.fr>'),
        new IncomingMessage('info@divioseo.fr', 'Réponse automatique : absent', $this->yesterday, '<r2@divioseo.fr>', automatic: true),
        new IncomingMessage('mailer-daemon@googlemail.com', 'Delivery Status Notification (Failure)', $this->yesterday, '<r3@google.com>', failedRecipients: ['info@spmi.fr']),
        new IncomingMessage('seb.perso@gmail.com', 'Re: Candidature stage', $this->yesterday, '<r4@gmail.com>', references: ['<Haby@gmail.com>']),
        new IncomingMessage('boss@refus.fr', 'Re: Candidature stage', $this->yesterday, '<r5@refus.fr>'),
    ];

    $stats = app(ReplyChecker::class)->check($this->user);

    expect($stats)->toBe(['replies' => 2, 'automatic' => 1, 'bounces' => 1])
        ->and($this->kiwo->refresh()->status)->toBe(ApplicationStatus::ReplyReceived)
        ->and($this->divioseo->refresh()->status)->toBe(ApplicationStatus::FollowedUp)
        ->and($this->divioseo->notes)->toContain('réponse automatique')
        ->and($this->spmi->refresh()->status)->toBe(ApplicationStatus::DoNotContact)
        ->and($this->haby->refresh()->status)->toBe(ApplicationStatus::ReplyReceived, 'reply from a personal address, linked by Message-ID')
        ->and($this->refused->refresh()->status)->toBe(ApplicationStatus::Negative, 'a status set by the student is never changed');
});

it('saves a reply draft to complete, never sends it', function () {
    $this->mailbox->inbox = [new IncomingMessage('marie.durand@kiwo.fr', 'Candidature stage', $this->yesterday, '<r1@kiwo.fr>')];

    app(ReplyChecker::class)->check($this->user);

    expect($this->mailbox->drafts)->toHaveCount(1)
        ->and($this->mailbox->drafts[0]['to'])->toBe('marie.durand@kiwo.fr')
        ->and($this->mailbox->drafts[0]['subject'])->toBe('Re: Candidature stage')
        ->and($this->mailbox->drafts[0]['inReplyTo'])->toBe('<r1@kiwo.fr>')
        ->and($this->mailbox->drafts[0]['body'])->toContain('À COMPLÉTER')
        ->and($this->mailbox->sent)->toBe([])
        ->and($this->kiwo->replies()->first()->draft_created)->toBeTrue();
});

it('does nothing twice when run again', function () {
    $this->mailbox->inbox = [new IncomingMessage('info@divioseo.fr', 'Out of office', $this->yesterday, '<r2@divioseo.fr>', automatic: true)];

    app(ReplyChecker::class)->check($this->user);
    app(ReplyChecker::class)->check($this->user);

    expect($this->divioseo->replies()->count())->toBe(1)
        ->and(substr_count($this->divioseo->refresh()->notes, 'réponse automatique'))->toBe(1);
});

it('ignores old newsletters and delay notices', function () {
    $this->mailbox->inbox = [
        new IncomingMessage('news@kiwo.fr', 'Notre newsletter', Carbon::now()->subDays(10), '<n@kiwo.fr>'),
        new IncomingMessage('mailer-daemon@googlemail.com', 'Delivery Status Notification (Delay)', $this->yesterday, '<d@google.com>'),
    ];

    app(ReplyChecker::class)->check($this->user);

    expect($this->kiwo->refresh()->status)->toBe(ApplicationStatus::Sent)
        ->and($this->spmi->refresh()->status)->toBe(ApplicationStatus::Sent);
});

it('stops the follow-up once a company has replied', function () {
    $this->travelTo(Carbon::parse('2026-10-20 09:00'));
    $this->mailbox->inbox = [new IncomingMessage('marie@kiwo.fr', 'Re: Candidature', Carbon::parse('2026-10-15'), '<r1@kiwo.fr>')];

    app(ReplyChecker::class)->check($this->user);
    app(\App\Services\ApplicationSender::class)->scheduleToday($this->user);

    expect($this->mailbox->recipients())->not->toContain('service.client@kiwo.fr');
});

it('records the reply type', function () {
    $this->mailbox->inbox = [new IncomingMessage('mailer-daemon@googlemail.com', 'Undeliverable', $this->yesterday, '<b@google.com>', failedRecipients: ['info@spmi.fr'])];

    app(ReplyChecker::class)->check($this->user);

    expect($this->spmi->replies()->first()->type)->toBe(ReplyType::Bounce);
});
