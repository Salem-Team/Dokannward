<?php

use App\Services\GeocodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('reverse geocodes a pin through the storefront geo API', function () {
    Cache::flush();
    Http::fake([
        'nominatim.openstreetmap.org/reverse*' => Http::response([
            'lat' => '30.0444200',
            'lon' => '31.2357120',
            'display_name' => 'Tahrir Square, Cairo, Egypt',
            'address' => [
                'road' => 'Tahrir Street',
                'house_number' => '12',
                'city' => 'Cairo',
                'postcode' => '11511',
            ],
        ], 200),
    ]);

    $this->getJson('/api/geo/reverse?lat=30.04442&lng=31.23571')
        ->assertOk()
        ->assertJsonPath('data.city', 'Cairo')
        ->assertJsonPath('data.line_1', '12 Tahrir Street')
        ->assertJsonPath('data.place_name', 'Tahrir Square, Cairo, Egypt');
});

it('searches Egyptian places for the checkout map', function () {
    Cache::flush();
    Http::fake([
        'nominatim.openstreetmap.org/search*' => Http::response([
            [
                'lat' => '31.2001',
                'lon' => '29.9187',
                'display_name' => 'Stanley, Alexandria, Egypt',
                'address' => [
                    'suburb' => 'Stanley',
                    'city' => 'Alexandria',
                ],
            ],
        ], 200),
    ]);

    $this->getJson('/api/geo/search?q=Stanley')
        ->assertOk()
        ->assertJsonPath('data.0.city', 'Alexandria')
        ->assertJsonPath('data.0.latitude', 31.2001);
});

it('returns null data when nominatim is unreachable', function () {
    Cache::flush();
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response('down', 503),
    ]);

    $this->getJson('/api/geo/reverse?lat=30&lng=31')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('validates reverse geocode coordinates', function () {
    $this->getJson('/api/geo/reverse?lat=200&lng=31')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lat']);
});

it('fills street and city from suburb plus governorate labels', function () {
    Cache::flush();
    Http::fake([
        'nominatim.openstreetmap.org/reverse*' => Http::response([
            'lat' => '30.0626',
            'lon' => '31.2197',
            'display_name' => 'Abu El Feda, Zamalek, Cairo Governorate, Egypt',
            'address' => [
                'road' => 'Abu El Feda',
                'suburb' => 'Zamalek',
                'state' => 'Cairo Governorate',
                'postcode' => '11211',
            ],
        ], 200),
    ]);

    $this->getJson('/api/geo/reverse?lat=30.0626&lng=31.2197')
        ->assertOk()
        ->assertJsonPath('data.city', 'Zamalek')
        ->assertJsonPath('data.line_1', 'Abu El Feda, Zamalek')
        ->assertJsonPath('data.postal_code', '11211');
});

it('maps Fifth Settlement pins to New Cairo instead of Cairo', function () {
    Cache::flush();
    Http::fake([
        'nominatim.openstreetmap.org/reverse*' => Http::response([
            'lat' => '30.0285035',
            'lon' => '31.43194',
            'display_name' => '186, Amr Ibn El Aas Street, Southern Academy Area E, Ganoob El Akademeya Districts, Cairo, Egypt',
            'address' => [
                'road' => 'Amr Ibn El Aas Street',
                'house_number' => '186',
                'suburb' => 'Southern Academy Area E',
                'city' => 'Cairo',
                'postcode' => '11865',
            ],
        ], 200),
    ]);

    $this->getJson('/api/geo/reverse?lat=30.0285&lng=31.4320')
        ->assertOk()
        ->assertJsonPath('data.city', 'New Cairo');
});

it('caches reverse geocode failures briefly instead of for a week', function () {
    Cache::flush();
    Http::fake([
        'nominatim.openstreetmap.org/reverse*' => Http::response('down', 503),
    ]);

    $service = app(GeocodingService::class);
    $key = 'geo.reverse.30.01000.31.40000';

    expect($service->reverse(30.01, 31.40))->toBeNull();
    expect(Cache::get($key))->toBeFalse();
});

it('returns a coarse IP-based location for checkout fallback', function () {
    Cache::flush();
    Http::fake([
        'ipwho.is/*' => Http::response([
            'success' => true,
            'latitude' => 30.03,
            'longitude' => 31.43,
            'city' => 'Cairo',
            'country_code' => 'EG',
        ], 200),
    ]);

    $place = app(GeocodingService::class)->locateFromIp('8.8.8.8');

    expect($place)->not->toBeNull()
        ->and($place['city'])->toBe('New Cairo')
        ->and($place['latitude'])->toBe(30.03);
});

it('maps nominatim payloads in the geocoding service', function () {
    Cache::flush();
    Http::fake([
        'nominatim.openstreetmap.org/reverse*' => Http::response([
            'lat' => '29.9600',
            'lon' => '31.2500',
            'display_name' => 'Maadi, Cairo',
            'address' => ['city' => 'Cairo', 'road' => 'Road 9'],
        ], 200),
    ]);

    $place = app(GeocodingService::class)->reverse(29.96, 31.25);

    expect($place)->not->toBeNull()
        ->and($place['city'])->toBe('Maadi')
        ->and($place['line_1'])->toBe('Road 9');
});
