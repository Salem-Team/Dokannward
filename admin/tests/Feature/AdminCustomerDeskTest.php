<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;

function customerDeskAdmin(): User
{
    $user = User::factory()->admin()->create([
        'email' => 'customer-desk-admin@example.com',
        'password' => 'SecurePass!234',
    ]);
    $user->forceFill(['is_admin' => true, 'is_active' => true, 'is_guest' => false])->save();

    return $user->fresh();
}

it('shows the customers desk with crm segments and order expand', function () {
    $admin = customerDeskAdmin();

    $buyer = User::factory()->create([
        'name' => 'Nadia Buyer',
        'email' => 'nadia.buyer@example.com',
        'is_admin' => false,
        'is_guest' => false,
        'is_active' => true,
    ]);

    $address = Address::create([
        'id' => (string) Str::uuid(),
        'user_id' => $buyer->id,
        'recipient_name' => 'Nadia Buyer',
        'phone' => '01009998888',
        'line_1' => '12 Nile St',
        'city' => 'Cairo',
        'postal_code' => '11511',
        'country' => 'EG',
        'latitude' => 30.0444,
        'longitude' => 31.2357,
        'place_name' => 'Tahrir Square, Cairo',
        'location_source' => 'map',
    ]);

    Order::create([
        'id' => (string) Str::uuid(),
        'order_number' => 'ZBR-CUST-001',
        'user_id' => $buyer->id,
        'customer_email' => $buyer->email,
        'status' => 'pending',
        'subtotal' => 1000,
        'shipping_amount' => 0,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'shipping_address_id' => $address->id,
        'placed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertSee('Commerce · CRM')
        ->assertSee('Customers')
        ->assertSee('Nadia Buyer')
        ->assertSee('View details')
        ->assertSee('Buyers')
        ->assertSee('ZBR-CUST-001')
        ->assertSee('Addresses')
        ->assertSee('12 Nile St')
        ->assertSee('Tahrir Square, Cairo')
        ->assertSee('Google Maps');
});

it('filters guest customers via segment', function () {
    $admin = customerDeskAdmin();

    User::factory()->create([
        'name' => 'Guest Shopper',
        'email' => 'guest.shopper@example.com',
        'is_admin' => false,
        'is_guest' => true,
        'is_active' => true,
    ]);

    User::factory()->create([
        'name' => 'Account Holder',
        'email' => 'account.holder@example.com',
        'is_admin' => false,
        'is_guest' => false,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.customers.index', ['segment' => 'guests']))
        ->assertOk()
        ->assertSee('Guest Shopper')
        ->assertDontSee('Account Holder');
});
