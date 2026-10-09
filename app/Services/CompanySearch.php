<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Finds companies around the student with the free government API
 * (recherche-entreprises.api.gouv.fr), adds them to the shared catalog,
 * and creates one "to review" application per company for this student.
 */
class CompanySearch
{
    private const API = 'https://recherche-entreprises.api.gouv.fr/near_point';

    /** Sole traders (nature juridique 1000) rarely take interns. */
    private const SOLE_TRADER = '1000';

    /** Headcount codes from INSEE: 11 = 10 to 19 employees and above. */
    private const HEADCOUNTS = [
        'NN' => '?', '00' => '0', '01' => '1-2', '02' => '3-5', '03' => '6-9', '11' => '10-19', '12' => '20-49',
        '21' => '50-99', '22' => '100-199', '31' => '200-249', '32' => '250-499', '41' => '500-999',
        '42' => '1000-1999', '51' => '2000-4999', '52' => '5000-9999', '53' => '10000+',
    ];

    /**
     * @return int number of new applications created
     */
    public function searchFor(User $user, int $maxPages = 40): int
    {
        $profile = $user->profile()->with('program.nafCodes')->firstOrFail();
        $priorities = $profile->program->nafCodes->mapWithKeys(fn ($naf) => [$naf->code => $naf->pivot->priority]);
        if ($priorities->isEmpty()) {
            return 0;
        }

        $created = 0;
        $page = 1;
        $totalPages = 1;
        while ($page <= min($totalPages, $maxPages)) {
            $data = $this->fetchPage($profile->latitude, $profile->longitude, $profile->radius_km, $priorities->keys()->all(), $page);
            if ($data === null) {
                break;
            }
            $totalPages = (int) ($data['total_pages'] ?? 1);
            foreach ($data['results'] ?? [] as $result) {
                $created += $this->importResult($user, $profile, $result, $priorities->all());
            }
            $page++;
            usleep(200_000); // the API allows about 7 requests per second
        }

        ActivityLog::record($user, 'company_search', "$created nouvelle(s) entreprise(s) trouvée(s) dans un rayon de {$profile->radius_km} km");

        return $created;
    }

    private function fetchPage(float $lat, float $lon, int $radius, array $nafCodes, int $page): ?array
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::timeout(30)->get(self::API, [
                    'lat' => $lat,
                    'long' => $lon,
                    'radius' => $radius,
                    'activite_principale' => implode(',', $nafCodes),
                    'per_page' => 25,
                    'page' => $page,
                ]);
            } catch (\Throwable) {
                return null;
            }
            if ($response->status() === 429) {
                sleep(2 * $attempt);

                continue;
            }

            return $response->successful() ? $response->json() : null;
        }

        return null;
    }

    private function importResult(User $user, $profile, array $result, array $priorities): int
    {
        if ((string) ($result['nature_juridique'] ?? '') === self::SOLE_TRADER || empty($result['siren'])) {
            return 0;
        }

        $establishment = $this->closestActiveEstablishment($result, $profile->latitude, $profile->longitude);
        if ($establishment === null || $establishment['distance'] > $profile->radius_km) {
            return 0;
        }

        $naf = $establishment['activite_principale'] ?? $result['activite_principale'] ?? null;
        $headcountCode = $establishment['tranche_effectif_salarie'] ?? $result['tranche_effectif_salarie'] ?? 'NN';

        $company = Company::firstOrNew(['siren' => $result['siren']]);
        $company->fill([
            'name' => mb_convert_case(mb_strtolower($result['nom_complet'] ?? $result['siren']), MB_CASE_TITLE),
            'naf_code' => $naf,
            'headcount' => self::HEADCOUNTS[$headcountCode] ?? $headcountCode,
            'address' => $establishment['adresse'] ?? null,
            'postal_code' => $establishment['code_postal'] ?? null,
            'city' => isset($establishment['libelle_commune']) ? mb_convert_case(mb_strtolower($establishment['libelle_commune']), MB_CASE_TITLE) : null,
            'latitude' => $establishment['latitude'],
            'longitude' => $establishment['longitude'],
        ]);
        $company->save();

        if ($company->blocked) {
            return 0;
        }

        $application = JobApplication::firstOrCreate(
            ['user_id' => $user->id, 'company_siren' => $company->siren],
            [
                'status' => ApplicationStatus::PendingReview,
                'priority' => $this->priority($priorities[$naf] ?? 'C', $headcountCode),
                'distance_km' => round($establishment['distance'], 1),
            ],
        );

        return $application->wasRecentlyCreated ? 1 : 0;
    }

    /**
     * The API returns the company and its establishments near the point: keep the closest open one.
     */
    private function closestActiveEstablishment(array $result, float $lat, float $lon): ?array
    {
        $candidates = $result['matching_etablissements'] ?? [];
        if (! $candidates && isset($result['siege'])) {
            $candidates = [$result['siege']];
        }

        $best = null;
        foreach ($candidates as $establishment) {
            if (($establishment['etat_administratif'] ?? 'A') !== 'A' || ! isset($establishment['latitude'], $establishment['longitude'])) {
                continue;
            }
            $establishment['latitude'] = (float) $establishment['latitude'];
            $establishment['longitude'] = (float) $establishment['longitude'];
            $establishment['distance'] = Geocoder::distanceKm($lat, $lon, $establishment['latitude'], $establishment['longitude']);
            if ($best === null || $establishment['distance'] < $best['distance']) {
                $best = $establishment;
            }
        }

        return $best;
    }

    /**
     * Program priority, adjusted by size: unknown or no employees = one level lower,
     * 10 employees or more (more chances to find a tutor) = one level higher.
     */
    public function priority(string $programPriority, string $headcountCode): string
    {
        $levels = ['A', 'B', 'C'];
        $index = array_search($programPriority, $levels, true);
        $index = $index === false ? 2 : $index;
        if (in_array($headcountCode, ['NN', '00', ''], true)) {
            $index++;
        } elseif ($headcountCode >= '11') {
            $index--;
        }

        return $levels[max(0, min(2, $index))];
    }
}
