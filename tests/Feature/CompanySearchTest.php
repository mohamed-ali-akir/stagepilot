<?php

use App\Enums\ApplicationStatus;
use App\Models\Company;
use App\Services\CompanySearch;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->user = student();
    $establishment = fn (string $naf, float $lat, float $lon, string $headcount = '12', string $state = 'A') => [
        'activite_principale' => $naf, 'tranche_effectif_salarie' => $headcount, 'etat_administratif' => $state,
        'adresse' => '1 RUE DE LA PAIX 71100 CHALON-SUR-SAONE', 'code_postal' => '71100', 'libelle_commune' => 'CHALON-SUR-SAONE',
        'latitude' => (string) $lat, 'longitude' => (string) $lon,
    ];
    Http::fake([
        'recherche-entreprises.api.gouv.fr/*' => Http::response([
            'total_pages' => 1,
            'results' => [
                ['siren' => '111111111', 'nom_complet' => 'ACME LOGICIELS', 'nature_juridique' => '5710', 'matching_etablissements' => [$establishment('62.01Z', 46.79, 4.86)]],
                ['siren' => '222222222', 'nom_complet' => 'JEAN DUPONT', 'nature_juridique' => '1000', 'matching_etablissements' => [$establishment('62.01Z', 46.79, 4.86)]],
                ['siren' => '333333333', 'nom_complet' => 'TROP LOIN', 'nature_juridique' => '5710', 'matching_etablissements' => [$establishment('62.01Z', 48.85, 2.35)]],
                ['siren' => '444444444', 'nom_complet' => 'FERMEE', 'nature_juridique' => '5710', 'matching_etablissements' => [$establishment('62.01Z', 46.79, 4.86, '12', 'F')]],
                ['siren' => '555555555', 'nom_complet' => 'DEPANNAGE PC', 'nature_juridique' => '5499', 'matching_etablissements' => [$establishment('95.11Z', 46.80, 4.85, 'NN')]],
            ],
        ]),
    ]);
});

it('adds nearby open companies and creates applications to review', function () {
    $created = app(CompanySearch::class)->searchFor($this->user);

    expect($created)->toBe(2)
        ->and(Company::pluck('siren')->sort()->values()->all())->toBe(['111111111', '555555555']);
    $acme = $this->user->jobApplications()->where('company_siren', '111111111')->first();
    expect($acme->status)->toBe(ApplicationStatus::PendingReview)
        ->and($acme->priority)->toBe('A')
        ->and($acme->distance_km)->toBeLessThan(5)
        ->and(Company::find('111111111')->name)->toBe('Acme Logiciels');
});

it('gives a lower priority to repair shops with unknown headcount', function () {
    app(CompanySearch::class)->searchFor($this->user);

    expect($this->user->jobApplications()->where('company_siren', '555555555')->value('priority'))->toBe('C');
});

it('never creates the same application twice', function () {
    app(CompanySearch::class)->searchFor($this->user);

    expect(app(CompanySearch::class)->searchFor($this->user))->toBe(0)
        ->and($this->user->jobApplications()->count())->toBe(2);
});

it('skips companies that asked not to be contacted', function () {
    Company::factory()->create(['siren' => '111111111', 'blocked' => true]);

    app(CompanySearch::class)->searchFor($this->user);

    expect($this->user->jobApplications()->where('company_siren', '111111111')->exists())->toBeFalse();
});
