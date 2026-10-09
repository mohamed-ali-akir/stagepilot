<?php

use App\Enums\ApplicationStatus;
use App\Models\Company;
use App\Models\JobApplication;
use App\Services\ApplicationPreparer;
use App\Services\Hooks\HookGenerator;
use App\Services\Hooks\HookPrompt;
use App\Services\Hooks\HookProposal;
use App\Services\Hooks\HookVerifier;
use App\Services\Hooks\MistralHookGenerator;
use App\Services\Hooks\OllamaHookGenerator;
use App\Services\Hooks\TemplateHookGenerator;
use Illuminate\Support\Facades\Http;

$site = "=== URL : https://acme.fr/ ===\nBienvenue chez Acme. Nous créons des boutiques en ligne et des sites e-commerce performants pour les commerçants de Bourgogne. Contactez-nous.\n\n"
    ."=== URL : https://acme.fr/agence ===\nNotre agence numérique accompagne les entreprises depuis quinze ans dans leurs projets digitaux.";

beforeEach(function () {
    $this->user = student();
    $this->company = Company::factory()->create(['name' => 'Acme', 'website' => 'https://acme.fr/']);
});

describe('template generator (free, no AI)', function () use ($site) {
    it('builds an honest hook from a sentence of the site', function () use ($site) {
        $proposal = (new TemplateHookGenerator)->propose($this->user, $this->company, $site);

        expect($proposal)->toBeInstanceOf(HookProposal::class)
            ->and($proposal->relevant)->toBeTrue()
            ->and($proposal->excerpt)->toContain('sites e-commerce')
            ->and($proposal->url)->toBe('https://acme.fr/')
            ->and($proposal->hook)->toContain('la création de sites e-commerce')
            ->and($proposal->hook)->toContain('en équipe')
            ->and($proposal->hook)->toContain('Nex Informatique')
            ->and($proposal->hook)->not->toContain('développé');
    });

    it('produces hooks that pass the same verification as the AI ones', function () use ($site) {
        Http::fake(['acme.fr*' => Http::response('<p>Bienvenue chez Acme. Nous créons des boutiques en ligne et des sites e-commerce performants pour les commerçants de Bourgogne. Contactez-nous.</p>')]);
        $proposal = (new TemplateHookGenerator)->propose($this->user, $this->company, $site);

        expect(app(HookVerifier::class)->check($this->company, $proposal->hook, $proposal->excerpt, $proposal->url))->toBe([]);
    });

    it('lets the student write the hook when no sentence can be used (instead of discarding the company)', function () {
        $answer = (new TemplateHookGenerator)->propose($this->user, $this->company, "=== URL : https://acme.fr/ ===\nNous fabriquons des portes et fenêtres en bois massif depuis 1950 en Saône-et-Loire.");

        expect($answer)->toBeString()->toContain('écris l\'accroche toi-même');
    });

    it('ignores privacy notices that only talk about "your data"', function () {
        $site = "=== URL : https://acme.fr/ ===\nVous pouvez accéder à vos données, les rectifier ou vous opposer à leur traitement en écrivant à la CNIL.";

        expect((new TemplateHookGenerator)->bestSentence($site))->toBeNull();
    });

    it('always waits for the student\'s approval, even in automatic mode', function () {
        $this->user->profile->update(['validation_mode' => 'auto_with_veto']);
        $application = JobApplication::factory()->for($this->user)->create(['company_siren' => $this->company->siren]);
        Http::fake(['acme.fr*' => Http::response('<p>Nous créons des boutiques en ligne et des sites e-commerce performants pour les commerçants de Bourgogne.</p>')]);
        app()->instance(HookGenerator::class, new TemplateHookGenerator);

        app(ApplicationPreparer::class)->prepareOne($this->user->fresh('profile'), $application);

        expect($application->refresh()->status)->toBe(ApplicationStatus::PendingReview)
            ->and($application->hook)->not->toBeNull();
    });

    it('picks the experience closest to the site', function () {
        $this->user->experiences()->create(['title' => 'Projet mobile', 'description' => 'Application mobile Android en Kotlin pour suivre les appels.', 'role' => 'solo', 'technologies' => 'Kotlin, Android']);
        $site = "=== URL : https://acme.fr/ ===\nNous développons des applications mobiles Android et iOS pour les PME de la région.";

        $proposal = (new TemplateHookGenerator)->propose($this->user->fresh('experiences'), $this->company, $site);

        expect($proposal->hook)->toContain('projet mobile')->toContain('en autonomie');
    });

    it('is the default, free engine', function () {
        config(['stagepilot.hook_provider' => null]);

        expect(app(HookGenerator::class))->toBeInstanceOf(TemplateHookGenerator::class);
    });
});

describe('AI engines', function () use ($site) {
    $answer = ['relevant' => true, 'reason' => '', 'hook' => 'Vos sites e-commerce m\'intéressent particulièrement, car j\'aimerais travailler sur des projets utilisés par des commerçants.', 'excerpt' => 'des sites e-commerce performants pour les commerçants', 'url' => 'https://acme.fr/'];

    it('asks Ollama for a structured answer and reads it', function () use ($site, $answer) {
        Http::fake(['localhost:11434/api/chat' => Http::response(['message' => ['content' => json_encode($answer)]])]);

        $proposal = (new OllamaHookGenerator('http://localhost:11434', 'qwen3:8b'))->propose($this->user, $this->company, $site);

        expect($proposal->hook)->toBe($answer['hook']);
        Http::assertSent(fn ($request) => $request['model'] === 'qwen3:8b' && $request['stream'] === false && isset($request['format']['properties']['excerpt'])
            && $request['options']['num_ctx'] >= 16384);
    });

    it('asks for the proof before the hook, so that the AI does not copy its own sentence as proof', function () {
        $fields = array_keys(HookPrompt::jsonSchema()['properties']);

        expect(array_search('excerpt', $fields))->toBeLessThan(array_search('hook', $fields));
    });

    it('always waits for the student\'s approval with a small local AI', function () use ($answer) {
        $this->user->profile->update(['validation_mode' => 'auto_with_veto']);
        $application = JobApplication::factory()->for($this->user)->create(['company_siren' => $this->company->siren]);
        Http::fake([
            'localhost:11434/*' => Http::response(['message' => ['content' => json_encode($answer)]]),
            'acme.fr*' => Http::response('<p>Nous créons des sites e-commerce performants pour les commerçants de Bourgogne.</p>'),
        ]);
        app()->instance(HookGenerator::class, new OllamaHookGenerator('http://localhost:11434', 'qwen3:8b'));

        app(ApplicationPreparer::class)->prepareOne($this->user->fresh('profile'), $application);

        expect($application->refresh()->status)->toBe(ApplicationStatus::PendingReview)
            ->and($application->hook)->toBe($answer['hook']);
    });

    it('asks Mistral for a structured answer and reads it', function () use ($site, $answer) {
        Http::fake(['api.mistral.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode($answer)]]]])]);

        $proposal = (new MistralHookGenerator('cle-de-test', 'mistral-small-latest'))->propose($this->user, $this->company, $site);

        expect($proposal->excerpt)->toBe($answer['excerpt']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer cle-de-test')
            && $request['response_format']['type'] === 'json_schema');
    });

    it('explains clearly when an engine is not available', function () use ($site) {
        Http::fake(['api.mistral.ai/*' => Http::response(['message' => 'Rate limit'], 429)]);

        expect((new MistralHookGenerator('cle', 'm'))->propose($this->user, $this->company, $site))->toContain('limite gratuite')
            ->and((new MistralHookGenerator(null, 'm'))->propose($this->user, $this->company, $site))->toContain('MISTRAL_API_KEY');
    });

    it('rejects an unreadable AI answer', function () use ($site) {
        Http::fake(['localhost:11434/api/chat' => Http::response(['message' => ['content' => 'Voici votre accroche : ...']])]);

        expect((new OllamaHookGenerator('http://localhost:11434', 'm'))->propose($this->user, $this->company, $site))->toContain('illisible');
    });
});
