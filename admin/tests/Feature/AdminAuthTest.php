<?php

use App\Models\User;
use App\Services\Auth\LoginAttemptLimiter;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    // Clear lockout keys between tests.
    cache()->flush();
});

function adminUser(array $overrides = []): User
{
    $user = User::factory()->admin()->create(array_merge([
        'email' => 'admin@example.com',
        'password' => 'SecurePass!234',
    ], $overrides));

    // is_admin is not mass-assignable — force it for tests.
    $user->forceFill(['is_admin' => true, 'is_active' => true, 'is_guest' => false])->save();

    return $user->fresh();
}

test('admin can sign in with valid credentials', function () {
    $user = adminUser();

    $response = $this->post('/admin/login', [
        'email' => $user->email,
        'password' => 'SecurePass!234',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('login fails with a generic message for wrong password', function () {
    $user = adminUser();

    $response = $this->from('/admin/login')->post('/admin/login', [
        'email' => $user->email,
        'password' => 'WrongPassword!1',
    ]);

    $response->assertRedirect('/admin/login');
    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toBe('The provided credentials do not match our records.');
    $this->assertGuest();
});

test('customer accounts cannot access the admin panel', function () {
    $customer = User::factory()->create([
        'email' => 'customer@example.com',
        'password' => 'SecurePass!234',
    ]);
    $customer->forceFill(['is_admin' => false])->save();

    $response = $this->from('/admin/login')->post('/admin/login', [
        'email' => $customer->email,
        'password' => 'SecurePass!234',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('inactive admins cannot sign in', function () {
    $user = adminUser(['email' => 'inactive@example.com']);
    $user->forceFill(['is_active' => false])->save();

    $this->from('/admin/login')->post('/admin/login', [
        'email' => $user->email,
        'password' => 'SecurePass!234',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('non-admin session is rejected by admin middleware', function () {
    $customer = User::factory()->create([
        'password' => 'SecurePass!234',
    ]);

    $this->actingAs($customer)
        ->get('/admin/dashboard')
        ->assertRedirect(route('admin.login'));

    $this->assertGuest();
});

test('login lockout triggers after repeated failures', function () {
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

    $user = adminUser(['email' => 'lockout@example.com']);

    for ($i = 0; $i < LoginAttemptLimiter::MAX_ATTEMPTS; $i++) {
        $this->from('/admin/login')->post('/admin/login', [
            'email' => $user->email,
            'password' => 'WrongPassword!1',
        ]);
    }

    $response = $this->from('/admin/login')->post('/admin/login', [
        'email' => $user->email,
        'password' => 'SecurePass!234',
    ]);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Too many failed attempts');
    $this->assertGuest();
});

test('security headers are present on the login page', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');

    $csp = (string) $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("img-src 'self' data: blob: https:");
});

test('keep me signed in issues a remember token cookie', function () {
    $user = adminUser(['email' => 'remember@example.com']);

    $response = $this->post('/admin/login', [
        'email' => $user->email,
        'password' => 'SecurePass!234',
        'remember' => '1',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->remember_token)->not->toBeNull();
    $response->assertCookie(\App\Http\Controllers\Admin\AuthController::EMAIL_HINT_COOKIE);
    $response->assertCookie(\App\Http\Controllers\Admin\AuthController::KEEP_SIGNED_IN_COOKIE, '1');
});

test('login without keep me signed in still remembers the email hint', function () {
    $user = adminUser(['email' => 'hint@example.com']);

    $response = $this->post('/admin/login', [
        'email' => $user->email,
        'password' => 'SecurePass!234',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $response->assertCookie(\App\Http\Controllers\Admin\AuthController::EMAIL_HINT_COOKIE);
    $response->assertCookie(\App\Http\Controllers\Admin\AuthController::KEEP_SIGNED_IN_COOKIE, '0');
});

test('login desk reads remembered email and keep-signed-in preference from cookies', function () {
    $request = \Illuminate\Http\Request::create('/admin/login', 'GET', [], [
        \App\Http\Controllers\Admin\AuthController::EMAIL_HINT_COOKIE => 'staff@dokannward.com',
        \App\Http\Controllers\Admin\AuthController::KEEP_SIGNED_IN_COOKIE => '1',
    ]);
    $request->setLaravelSession(app('session.store'));

    $view = app(\App\Http\Controllers\Admin\AuthController::class)->showLogin($request);

    expect($view->getData()['rememberedEmail'])->toBe('staff@dokannward.com');
    expect($view->getData()['keepSignedIn'])->toBeTrue();
});

test('admin can change password with current password confirmation', function () {
    $user = adminUser(['email' => 'profile@example.com']);

    $this->actingAs($user)
        ->put('/admin/profile/password', [
            'current_password' => 'SecurePass!234',
            'password' => 'NewSecurePass!567',
            'password_confirmation' => 'NewSecurePass!567',
        ])
        ->assertRedirect();

    expect(Hash::check('NewSecurePass!567', $user->fresh()->password))->toBeTrue();
});

test('password change rejects incorrect current password', function () {
    $user = adminUser(['email' => 'profile2@example.com']);

    $this->actingAs($user)
        ->from('/admin/profile')
        ->put('/admin/profile/password', [
            'current_password' => 'WrongCurrent!1',
            'password' => 'NewSecurePass!567',
            'password_confirmation' => 'NewSecurePass!567',
        ])
        ->assertSessionHasErrors('current_password');
});
