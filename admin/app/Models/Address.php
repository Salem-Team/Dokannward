<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'label',
        'recipient_name',
        'phone',
        'line_1',
        'line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'place_name',
        'location_source',
        'is_default_shipping',
        'is_default_billing',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_default_shipping' => 'boolean',
        'is_default_billing' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shippingOrders()
    {
        return $this->hasMany(Order::class, 'shipping_address_id');
    }

    public function billingOrders()
    {
        return $this->hasMany(Order::class, 'billing_address_id');
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function oneLine(): string
    {
        $parts = array_filter([
            $this->line_1,
            $this->line_2,
            $this->city,
            $this->postal_code ?: null,
            $this->country,
        ], fn ($part) => filled($part));

        return implode(', ', $parts);
    }

    public function googleMapsUrl(): ?string
    {
        if (! $this->hasCoordinates()) {
            return null;
        }

        return sprintf(
            'https://www.google.com/maps?q=%s,%s',
            rawurlencode((string) $this->latitude),
            rawurlencode((string) $this->longitude),
        );
    }

    public function appleMapsUrl(): ?string
    {
        if (! $this->hasCoordinates()) {
            return null;
        }

        $query = $this->place_name ?: $this->oneLine();

        return sprintf(
            'https://maps.apple.com/?ll=%s,%s&q=%s',
            rawurlencode((string) $this->latitude),
            rawurlencode((string) $this->longitude),
            rawurlencode($query !== '' ? $query : 'Delivery pin'),
        );
    }

    public function osmUrl(): ?string
    {
        if (! $this->hasCoordinates()) {
            return null;
        }

        $lat = $this->latitude;
        $lng = $this->longitude;

        return sprintf(
            'https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=17/%s/%s',
            rawurlencode((string) $lat),
            rawurlencode((string) $lng),
            rawurlencode((string) $lat),
            rawurlencode((string) $lng),
        );
    }

    public function osmTileUrl(int $zoom = 15): ?string
    {
        if (! $this->hasCoordinates()) {
            return null;
        }

        $n = 2 ** $zoom;
        $x = (int) floor((($this->longitude + 180.0) / 360.0) * $n);
        $latRad = deg2rad((float) $this->latitude);
        $y = (int) floor((1 - log(tan($latRad) + (1 / cos($latRad))) / M_PI) / 2 * $n);
        $max = $n - 1;
        $x = max(0, min($max, $x));
        $y = max(0, min($max, $y));

        return "https://tile.openstreetmap.org/{$zoom}/{$x}/{$y}.png";
    }
}
