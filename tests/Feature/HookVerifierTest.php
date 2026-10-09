<?php

use App\Models\Company;
use App\Services\Hooks\HookVerifier;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Kiwo', 'website' => 'https://kiwo.fr/']);
    Http::fake([
        'kiwo.fr/logiciels*' => Http::response('<html><body><h1>Logiciels</h1><p>Nos logiciels sont développés en interne pour les EHPAD et les SSIAD, de la facturation au dossier de soins.</p></body></html>'),
        '*' => Http::response('', 404),
    ]);
    $this->hook = 'Vos logiciels pour les EHPAD et les SSIAD m\'intéressent, car j\'aimerais travailler sur des applications de gestion utilisées au quotidien.';
});

it('accepts a hook whose excerpt is really on the company site', function () {
    $problems = app(HookVerifier::class)->check($this->company, $this->hook, 'développés en interne pour les EHPAD et les SSIAD', 'https://kiwo.fr/logiciels');

    expect($problems)->toBe([]);
});

it('rejects an invented excerpt', function () {
    $problems = app(HookVerifier::class)->check($this->company, $this->hook, 'leader européen des logiciels pour hôpitaux et cliniques', 'https://kiwo.fr/logiciels');

    expect(implode(' ', $problems))->toContain('inventée');
});

it('rejects a proof taken from another website', function () {
    $problems = app(HookVerifier::class)->check($this->company, $this->hook, 'développés en interne pour les EHPAD et les SSIAD', 'https://autre-site.fr/');

    expect(implode(' ', $problems))->toContain('site de l\'entreprise');
});

it('rejects a hook without proof', function () {
    $problems = app(HookVerifier::class)->check($this->company, $this->hook, '', 'https://kiwo.fr/logiciels');

    expect(implode(' ', $problems))->toContain('preuve obligatoire');
});

it('rejects a hook unrelated to its excerpt', function () {
    $offTopic = 'Vos projets de jeux vidéo en réalité virtuelle m\'intéressent, car j\'aimerais découvrir le métier de développeur dans un studio.';
    $problems = app(HookVerifier::class)->check($this->company, $offTopic, 'développés en interne pour les EHPAD et les SSIAD', 'https://kiwo.fr/logiciels');

    expect(implode(' ', $problems))->toContain('ne reprend rien');
});
