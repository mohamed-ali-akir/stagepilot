<?php

use App\Enums\ApplicationStatus;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\JobApplication;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->student = student();
    $this->mine = JobApplication::factory()->for($this->student)->count(3)->create(['status' => ApplicationStatus::Sent, 'sent_at' => now()->subDays(2)]);
    $this->other = JobApplication::factory()->for(student())->create(['status' => ApplicationStatus::Sent]);
});

describe('authentication', function () {
    it('refuses visitors with HTTP 401 in JSON', function (string $method, string $uri) {
        $this->json($method, $uri)->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    })->with([
        ['GET', '/api/v1/applications'],
        ['GET', '/api/v1/stats'],
        ['PATCH', '/api/v1/applications/1/status'],
    ]);

    it('accepts a personal token (Authorization: Bearer)', function () {
        $token = $this->student->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/applications')->assertOk()->assertJsonCount(3, 'data');
    });

    it('creates a token with an artisan command, never for the demo account', function () {
        $this->artisan('stagepilot:api-token', ['email' => $this->student->email])->assertSuccessful();
        $this->artisan('stagepilot:api-token', ['email' => 'inconnu@example.com'])->assertFailed();

        expect($this->student->tokens()->count())->toBe(1);
    });
});

describe('GET /api/v1/applications', function () {
    it('lists only the student\'s own applications, with their company, paginated', function () {
        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/applications?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonStructure(['data' => [['id', 'status' => ['value', 'label', 'color'], 'company' => ['siren', 'name', 'city'], 'sent_at']], 'links', 'meta'])
            ->assertJsonMissing(['id' => $this->other->id]);
    });

    it('filters by status and by company name or city', function () {
        Sanctum::actingAs($this->student);
        $target = JobApplication::factory()->for($this->student)->create([
            'status' => ApplicationStatus::Interview,
            'company_siren' => Company::factory()->create(['name' => 'Vinium', 'city' => 'Beaune'])->siren,
        ]);

        $this->getJson('/api/v1/applications?status=interview,positive')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
        $this->getJson('/api/v1/applications?search=beaune')->assertJsonCount(1, 'data')->assertJsonPath('data.0.company.name', 'Vinium');
    });

    it('rejects an unknown status filter with HTTP 422', function () {
        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/applications?status=sent,nimporte')->assertUnprocessable();
    });

    it('never exposes the student\'s Gmail password nor internal notes', function () {
        Sanctum::actingAs($this->student);

        $json = $this->getJson('/api/v1/applications')->getContent();

        expect($json)->not->toContain('gmail_app_password')->not->toContain('notes')->not->toContain('abcdefghijklmnop');
    });
});

describe('GET /api/v1/applications/{id}', function () {
    it('shows one of the student\'s applications', function () {
        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/applications/'.$this->mine[0]->id)->assertOk()->assertJsonPath('data.id', $this->mine[0]->id);
    });

    it('forbids another student\'s application with HTTP 403', function () {
        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/applications/'.$this->other->id)->assertForbidden();
    });

    it('answers HTTP 404 for an application that does not exist', function () {
        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/applications/999999')->assertNotFound();
    });
});

describe('PATCH /api/v1/applications/{id}/status', function () {
    it('sets a status chosen by the student and logs it', function () {
        Sanctum::actingAs($this->student);
        $application = $this->mine[0];

        $this->patchJson("/api/v1/applications/{$application->id}/status", ['status' => 'interview'])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'interview')
            ->assertJsonPath('data.status.label', 'Entretien');

        expect($application->fresh()->status)->toBe(ApplicationStatus::Interview)
            ->and(ActivityLog::where('action', 'status_changed')->where('job_application_id', $application->id)->exists())->toBeTrue();
    });

    it('applies the same business rules as the screen ("ne pas contacter" blocks the company for everyone)', function () {
        Sanctum::actingAs($this->student);
        $application = $this->mine[0];

        $this->patchJson("/api/v1/applications/{$application->id}/status", ['status' => 'do_not_contact'])->assertOk();
        $this->patchJson("/api/v1/applications/{$this->mine[1]->id}/status", ['status' => 'internship_secured'])->assertOk();

        expect($application->company->fresh()->blocked)->toBeTrue()
            ->and($this->student->profile->fresh()->paused)->toBeTrue()
            ->and($this->student->profile->fresh()->internship_found_at)->not->toBeNull();
    });

    it('refuses a status the application sets itself, with HTTP 422', function (string $status) {
        Sanctum::actingAs($this->student);

        $this->patchJson("/api/v1/applications/{$this->mine[0]->id}/status", ['status' => $status])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    })->with(['sent', 'ready', 'pending_review', '', 'nimporte']);

    it('forbids changing another student\'s application, with HTTP 403', function () {
        Sanctum::actingAs($this->student);

        $this->patchJson("/api/v1/applications/{$this->other->id}/status", ['status' => 'interview'])->assertForbidden();

        expect($this->other->fresh()->status)->toBe(ApplicationStatus::Sent);
    });
});

describe('GET /api/v1/companies', function () {
    it('lists only the companies the student applied to', function () {
        Sanctum::actingAs($this->student);

        $this->getJson('/api/v1/companies')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonMissing(['siren' => $this->other->company_siren]);
    });
});

it('limits each student to 60 requests a minute (HTTP 429)', function () {
    Sanctum::actingAs($this->student);

    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/v1/companies')->assertOk();
    }

    $this->getJson('/api/v1/companies')->assertTooManyRequests();
});
