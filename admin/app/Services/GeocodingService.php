<?php

namespace App\Services;

use App\Support\EgyptCity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Server-side Nominatim proxy so the storefront never talks to OSM
 * directly (User-Agent policy, caching, rate control).
 */
class GeocodingService
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org';
    private const UA = 'DokanWardStorefront/1.0 (hello@dokannward.com)';
    private const TTL_SECONDS = 60 * 60 * 24 * 7;

    /**
     * @return array{place_name: ?string, line_1: ?string, city: ?string, postal_code: ?string, latitude: float, longitude: float}|null
     */
    public function reverse(float $latitude, float $longitude): ?array
    {
        $key = sprintf('geo.reverse.%.5f.%.5f', $latitude, $longitude);
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        $payload = $this->get('/reverse', [
            'lat' => $latitude,
            'lon' => $longitude,
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'zoom' => 18,
        ]);

        if (! $payload) {
            // Never cache hard failures for a week — Nominatim blips should recover quickly.
            Cache::put($key, false, 60);

            return null;
        }

        $mapped = $this->mapResult($payload, $latitude, $longitude);
        Cache::put($key, $mapped, self::TTL_SECONDS);

        return $mapped;
    }

    /**
     * @return list<array{place_name: string, line_1: ?string, city: ?string, postal_code: ?string, latitude: float, longitude: float}>
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $key = 'geo.search.'.md5(mb_strtolower($query));

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $payload = $this->get('/search', [
            'q' => $query,
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'limit' => 6,
            'countrycodes' => 'eg',
        ]);

        if (! is_array($payload)) {
            Cache::put($key, [], 60);

            return [];
        }

        $rows = [];
        foreach ($payload as $hit) {
            if (! is_array($hit)) {
                continue;
            }
            $mapped = $this->mapResult($hit);
            if ($mapped && $mapped['place_name']) {
                $rows[] = $mapped;
            }
        }

        Cache::put($key, $rows, self::TTL_SECONDS);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $hit
     * @return array{place_name: ?string, line_1: ?string, city: ?string, postal_code: ?string, latitude: float, longitude: float}|null
     */
    private function mapResult(array $hit, ?float $fallbackLat = null, ?float $fallbackLng = null): ?array
    {
        $lat = isset($hit['lat']) ? (float) $hit['lat'] : $fallbackLat;
        $lng = isset($hit['lon']) ? (float) $hit['lon'] : $fallbackLng;

        if ($lat === null || $lng === null) {
            return null;
        }

        $address = is_array($hit['address'] ?? null) ? $hit['address'] : [];
        $road = $this->firstFilled($address, ['road', 'pedestrian', 'residential']);
        $area = $this->firstFilled($address, ['neighbourhood', 'suburb', 'quarter', 'hamlet']);
        $house = $this->firstFilled($address, ['house_number']);
        $place = trim((string) ($hit['display_name'] ?? ''));
        if (mb_strlen($place) > 220) {
            $place = mb_substr($place, 0, 217).'…';
        }

        $city = EgyptCity::normalize(
            $this->firstFilled($address, ['city', 'town', 'village', 'municipality', 'county', 'state']),
        ) ?: EgyptCity::fromNominatim($address);
        $city = EgyptCity::refine($city, $address, $lat, $lng, $place !== '' ? $place : null);
        $postcode = $this->firstFilled($address, ['postcode']);

        $line1 = trim(implode(' ', array_filter([$house, $road])));
        if ($area && $line1 !== '' && ! str_contains(mb_strtolower($line1), mb_strtolower($area))) {
            $line1 .= ', '.$area;
        } elseif ($line1 === '') {
            $line1 = (string) ($area ?? '');
        }
        if ($line1 === '' && $place !== '') {
            $segments = array_values(array_filter(array_map('trim', explode(',', $place))));
            $line1 = implode(', ', array_slice($segments, 0, 2));
        }

        return [
            'place_name' => $place !== '' ? $place : null,
            'line_1' => $line1 !== '' ? $line1 : null,
            'city' => $city,
            'postal_code' => $postcode,
            'latitude' => round($lat, 7),
            'longitude' => round($lng, 7),
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     * @param  list<string>  $keys
     */
    private function firstFilled(array $address, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($address[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>|list<mixed>|null
     */
    private function get(string $path, array $query): mixed
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => self::UA,
                    'Accept-Language' => 'en,ar',
                ])
                    ->timeout(8)
                    ->connectTimeout(4)
                    ->get(self::ENDPOINT.$path, $query);

                if ($response->status() === 429) {
                    usleep(350_000);

                    continue;
                }

                if (! $response->successful()) {
                    return null;
                }

                return $response->json();
            } catch (\Throwable) {
                if ($attempt === 0) {
                    usleep(200_000);

                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Coarse IP-based location for laptops/desktops when GPS is unavailable.
     *
     * @return array{place_name: ?string, line_1: ?string, city: ?string, postal_code: ?string, latitude: float, longitude: float}|null
     */
    public function locateFromIp(string $ip): ?array
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $key = 'geo.ip.'.md5($ip);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached === [] ? null : $cached;
        }

        try {
            $response = Http::timeout(4)
                ->connectTimeout(2)
                ->get('https://ipwho.is/'.$ip);

            if (! $response->successful()) {
                Cache::put($key, [], 60);

                return null;
            }

            $payload = $response->json();
            if (! is_array($payload) || empty($payload['success'])) {
                Cache::put($key, [], 60);

                return null;
            }

            $lat = isset($payload['latitude']) ? (float) $payload['latitude'] : 0.0;
            $lng = isset($payload['longitude']) ? (float) $payload['longitude'] : 0.0;
            if ($lat === 0.0 && $lng === 0.0) {
                Cache::put($key, [], 60);

                return null;
            }

            $rawCity = isset($payload['city']) ? (string) $payload['city'] : null;
            $city = EgyptCity::refine(
                EgyptCity::normalize($rawCity),
                [],
                $lat,
                $lng,
                $rawCity,
            ) ?? EgyptCity::normalize($rawCity);

            $mapped = [
                'place_name' => $rawCity ? trim($rawCity.', Egypt') : null,
                'line_1' => null,
                'city' => $city,
                'postal_code' => null,
                'latitude' => round($lat, 7),
                'longitude' => round($lng, 7),
            ];

            Cache::put($key, $mapped, 3600);

            return $mapped;
        } catch (\Throwable) {
            Cache::put($key, [], 60);

            return null;
        }
    }
}
