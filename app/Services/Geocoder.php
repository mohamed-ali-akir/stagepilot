<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Turns a French address into GPS coordinates with the free government API (api-adresse.data.gouv.fr).
 */
class Geocoder
{
    /**
     * @return array{latitude: float, longitude: float, city: string, label: string}|null
     */
    public function locate(string $address): ?array
    {
        try {
            $response = Http::timeout(10)->get('https://api-adresse.data.gouv.fr/search/', [
                'q' => $address,
                'limit' => 1,
            ]);
        } catch (\Throwable) {
            return null;
        }

        $feature = $response->json('features.0');
        if (! $response->successful() || ! $feature) {
            return null;
        }

        [$longitude, $latitude] = $feature['geometry']['coordinates'];

        return [
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'city' => (string) ($feature['properties']['city'] ?? ''),
            'label' => (string) ($feature['properties']['label'] ?? $address),
        ];
    }

    /** Distance in km between two GPS points (haversine formula). */
    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $earthRadius * asin(sqrt($a));
    }
}
