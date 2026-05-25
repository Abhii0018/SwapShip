<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Free geocoding + routing helper.
 *
 * - Geocoding: Nominatim (OpenStreetMap) — no API key, attribution required.
 * - Routing: OpenRouteService (free 2000 req/day) — needs ORS_API_KEY.
 *
 * Every external call has a short timeout and is wrapped so a failure
 * returns null instead of throwing. Successful lookups are cached.
 */
class GeocodingService
{
    /**
     * Geocode a free-form address.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocodeAddress(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        $cacheKey = 'geo:nominatim:'.md5(strtolower($address));
        $ttlSeconds = (int) config('shipping.geocoding.cache_ttl_seconds', 86400);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['lat'], $cached['lng'])) {
            return $cached;
        }

        $userAgent = (string) config('shipping.geocoding.nominatim_user_agent', 'SwapShip/1.0');

        try {
            $response = Http::timeout(6)
                ->withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept-Language' => 'en',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                ]);

            if (! $response->successful()) {
                Log::warning('Nominatim geocode non-2xx', [
                    'address' => $address,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();
            if (! is_array($data) || empty($data[0]['lat']) || empty($data[0]['lon'])) {
                return null;
            }

            $result = [
                'lat' => (float) $data[0]['lat'],
                'lng' => (float) $data[0]['lon'],
            ];

            Cache::put($cacheKey, $result, $ttlSeconds);

            return $result;
        } catch (Throwable $exception) {
            Log::warning('Nominatim geocode failed', [
                'address' => $address,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get a driving route between two points using OpenRouteService.
     *
     * @param  array{lat: float, lng: float}  $from
     * @param  array{lat: float, lng: float}  $to
     * @return array{polyline: array<int, array{lat: float, lng: float}>, distance_m: float, duration_s: float}|null
     */
    public function getRoute(array $from, array $to): ?array
    {
        $apiKey = trim((string) config('shipping.geocoding.ors_api_key', ''));
        if ($apiKey === '') {
            return null;
        }

        if (! isset($from['lat'], $from['lng'], $to['lat'], $to['lng'])) {
            return null;
        }

        $cacheKey = sprintf(
            'route:ors:%s:%s',
            md5($from['lat'].','.$from['lng']),
            md5($to['lat'].','.$to['lng'])
        );
        $ttlSeconds = (int) config('shipping.geocoding.cache_ttl_seconds', 86400);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['polyline'], $cached['distance_m'])) {
            return $cached;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'Authorization' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openrouteservice.org/v2/directions/driving-car/geojson', [
                    'coordinates' => [
                        [(float) $from['lng'], (float) $from['lat']],
                        [(float) $to['lng'], (float) $to['lat']],
                    ],
                    'instructions' => false,
                ]);

            if (! $response->successful()) {
                Log::warning('ORS routing non-2xx', [
                    'status' => $response->status(),
                    'body' => mb_substr((string) $response->body(), 0, 250),
                ]);

                return null;
            }

            $feature = $response->json('features.0') ?? null;
            if (! is_array($feature)) {
                return null;
            }

            $coords = $feature['geometry']['coordinates'] ?? null;
            $summary = $feature['properties']['summary'] ?? null;

            if (! is_array($coords) || empty($coords)) {
                return null;
            }

            $polyline = array_values(array_filter(array_map(
                static function ($point) {
                    if (! is_array($point) || count($point) < 2) {
                        return null;
                    }

                    return [
                        'lat' => (float) $point[1],
                        'lng' => (float) $point[0],
                    ];
                },
                $coords
            )));

            if (empty($polyline)) {
                return null;
            }

            $result = [
                'polyline' => $polyline,
                'distance_m' => (float) ($summary['distance'] ?? 0),
                'duration_s' => (float) ($summary['duration'] ?? 0),
            ];

            Cache::put($cacheKey, $result, $ttlSeconds);

            return $result;
        } catch (Throwable $exception) {
            Log::warning('ORS routing failed', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
