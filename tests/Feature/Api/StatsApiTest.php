<?php

use App\Enums\ApplicationStatus;
use App\Enums\ReplyType;
use App\Models\Company;
use App\Models\JobApplication;
use Laravel\Sanctum\Sanctum;

it('computes the reply rate, the average reply delay, and the numbers per week and per sector', function () {
    $this->travelTo(now()->startOfWeek()->addDays(2)->setTime(12, 0));
    $student = student();
    // 4 applications sent (one per company): 2 real replies (after 2 and 4 days), 1 automatic reply (does not count), 1 silence
    $make = fn (string $activity, int $daysAgo) => JobApplication::factory()->for($student)->create([
        'company_siren' => Company::factory()->create(['activity' => $activity])->siren,
        'status' => ApplicationStatus::Sent,
        'sent_at' => now()->subDays($daysAgo),
    ]);
    $a = $make('Agence web', 10);
    $b = $make('Agence web', 9);
    $c = $make('Édition de logiciels', 1);
    $make('Édition de logiciels', 1);
    $a->replies()->create(['type' => ReplyType::Reply, 'sender' => 'x@web.fr', 'received_at' => $a->sent_at->addDays(2)]);
    $b->replies()->create(['type' => ReplyType::Reply, 'sender' => 'y@web.fr', 'received_at' => $b->sent_at->addDays(4)]);
    $c->replies()->create(['type' => ReplyType::AutoReply, 'sender' => 'z@soft.fr', 'received_at' => $c->sent_at->addHour()]);
    JobApplication::factory()->for($student)->create(); // not sent yet: ignored by the rates

    Sanctum::actingAs($student);
    $data = $this->getJson('/api/v1/stats')->assertOk()->json('data');

    expect($data['sent'])->toBe(4)
        ->and($data['answered'])->toBe(2)
        ->and($data['reply_rate'])->toEqual(50.0)
        ->and($data['average_reply_days'])->toEqual(3.0)
        ->and($data['by_status']['sent'])->toBe(4)
        ->and($data['by_status']['pending_review'])->toBe(1)
        ->and($data['by_week'])->toHaveCount(8)
        ->and(end($data['by_week'])['sent'])->toBe(2)
        ->and(collect($data['by_sector'])->firstWhere('sector', 'Agence web'))->toMatchArray(['sent' => 2, 'answered' => 2])
        ->and(collect($data['by_sector'])->firstWhere('sector', 'Édition de logiciels'))->toMatchArray(['sent' => 2, 'answered' => 0]);
});

it('gives empty rates rather than dividing by zero when nothing was sent', function () {
    Sanctum::actingAs(student());

    $this->getJson('/api/v1/stats')
        ->assertOk()
        ->assertJsonPath('data.sent', 0)
        ->assertJsonPath('data.reply_rate', null)
        ->assertJsonPath('data.average_reply_days', null);
});
