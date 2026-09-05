<?php

namespace App\Services;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Str;

/**
 * Resolves the CRM row behind a storefront checkout so shipping pins
 * land on the Customers desk — not only on the order itself.
 */
class GuestCustomerService
{
    /**
     * @param  array{recipient_name: string, phone: string, email?: ?string}  $data
     */
    public function findOrCreate(array $data): User
    {
        $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : '';
        $phone = trim((string) $data['phone']);
        $name = trim((string) $data['recipient_name']);
        $local = Phone::egyptianLocal($phone);

        $existing = $this->match($email, $local);

        if ($existing) {
            $dirty = false;

            if ($name !== '' && blank($existing->name)) {
                $existing->name = $name;
                $dirty = true;
            }

            if ($phone !== '' && blank($existing->phone)) {
                $existing->phone = $phone;
                $dirty = true;
            }

            if ($email !== '' && (
                blank($existing->email)
                || ($existing->is_guest && str_ends_with((string) $existing->email, '@checkout.dokannward'))
            )) {
                $existing->email = $email;
                $existing->normalized_email = $email;
                $dirty = true;
            }

            if ($dirty) {
                $existing->save();
            }

            return $existing;
        }

        $guestEmail = $email !== '' ? $email : $this->syntheticEmail($local, $phone);
        $taken = User::query()
            ->where(function ($q) use ($guestEmail) {
                $q->where('email', $guestEmail)
                    ->orWhere('normalized_email', strtolower($guestEmail));
            })
            ->first();

        if ($taken && $taken->is_admin !== true) {
            return $taken;
        }

        if ($taken) {
            $guestEmail = $this->syntheticEmail($local, $phone.'-'.Str::lower(Str::random(4)));
        }

        $guest = new User;
        $guest->forceFill([
            'id' => (string) Str::uuid(),
            'name' => $name !== '' ? $name : 'Guest',
            'email' => $guestEmail,
            'normalized_email' => strtolower($guestEmail),
            'phone' => $phone !== '' ? $phone : null,
            'is_guest' => true,
            'is_active' => true,
        ]);
        $guest->save();

        return $guest;
    }

    private function match(string $email, ?string $local): ?User
    {
        if ($email === '' && ! $local) {
            return null;
        }

        $query = User::query()->where('is_admin', false);

        $query->where(function ($q) use ($email, $local) {
            if ($email !== '') {
                $q->orWhere('normalized_email', $email)->orWhere('email', $email);
            }

            if ($local) {
                $q->orWhere('phone', 'like', '%'.$local.'%');
            }
        });

        return $query
            ->orderBy('is_guest')
            ->orderBy('created_at')
            ->first();
    }

    private function syntheticEmail(?string $local, string $phone): string
    {
        $key = $local ?: (Phone::digits($phone) ?: Str::lower(Str::random(10)));

        return 'guest.'.$key.'@checkout.dokannward';
    }
}
