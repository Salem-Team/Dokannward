<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\LoginAttemptLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Dummy bcrypt hash used when the email is unknown so Hash::check still runs
     * (mitigates user-enumeration via response timing).
     */
    private const DUMMY_PASSWORD_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    /** Encrypted device hint so returning staff see their email prefilled. */
    public const EMAIL_HINT_COOKIE = 'dokannward_admin_email';

    public const KEEP_SIGNED_IN_COOKIE = 'dokannward_admin_keep';

    /**
     * Render the admin sign-in desk with remembered device preferences.
     */
    public function showLogin(Request $request)
    {
        $rememberedEmail = old('email', (string) $request->cookie(self::EMAIL_HINT_COOKIE, ''));
        $keepSignedIn = old('remember') !== null
            ? (bool) old('remember')
            : $request->cookie(self::KEEP_SIGNED_IN_COOKIE) === '1';

        return view('admin.auth.login', [
            'rememberedEmail' => is_string($rememberedEmail) ? $rememberedEmail : '',
            'keepSignedIn' => $keepSignedIn,
        ]);
    }

    /**
     * Authenticate an admin with OWASP-aligned controls:
     * rate-limit/lockout, staff-only gate, active account check,
     * session fixation protection, and audit logging.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:filter', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $email = strtolower(trim($credentials['email']));
        $remember = $request->boolean('remember');
        $limiter = LoginAttemptLimiter::for($email, $request->ip() ?? '0.0.0.0');

        if ($limiter->tooManyAttempts()) {
            $minutes = max(1, (int) ceil($limiter->remainingSeconds() / 60));

            Log::notice('admin.login.blocked_lockout', [
                'email' => $email,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => ["Too many failed attempts. Try again in {$minutes} minute(s)."],
            ]);
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        $passwordValid = Hash::check(
            $credentials['password'],
            $user?->getAuthPassword() ?: self::DUMMY_PASSWORD_HASH
        );

        if (! $user || ! $passwordValid || ! $user->canAccessAdmin()) {
            $limiter->hit();

            Log::notice('admin.login.failed', [
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'reason' => ! $user
                    ? 'unknown_user'
                    : (! $passwordValid ? 'bad_password' : 'not_admin_or_inactive'),
                'attempts' => $limiter->attempts(),
            ]);

            // Generic message — never disclose which check failed (enumeration).
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $limiter->clear();

        // Staff "Keep me signed in" — 30-day recaller (set in AppServiceProvider).
        Auth::login($user, $remember);
        $request->session()->regenerate();

        // Always remember the email on this device for a polished return visit.
        cookie()->queue(
            cookie(
                name: self::EMAIL_HINT_COOKIE,
                value: $email,
                minutes: 60 * 24 * 180,
                path: '/',
                secure: (bool) config('session.secure'),
                httpOnly: true,
                raw: false,
                sameSite: config('session.same_site', 'lax'),
            )
        );

        // Persist the keep-signed-in preference so the checkbox feels personal.
        cookie()->queue(
            cookie(
                name: self::KEEP_SIGNED_IN_COOKIE,
                value: $remember ? '1' : '0',
                minutes: 60 * 24 * 180,
                path: '/',
                secure: (bool) config('session.secure'),
                httpOnly: true,
                raw: false,
                sameSite: config('session.same_site', 'lax'),
            )
        );

        $user->forceFill(['last_login_at' => now()])->save();

        Log::info('admin.login.success', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'remember' => $remember,
        ]);

        $intended = $request->session()->pull('url.intended');
        if (is_string($intended) && str_contains(parse_url($intended, PHP_URL_PATH) ?? '', '/admin')) {
            return redirect()->to($intended)->with('success', 'Welcome back!');
        }

        return redirect()->route('admin.dashboard')
            ->with('success', 'Welcome back!');
    }

    /**
     * Invalidate the session and rotate the CSRF token on logout.
     */
    public function logout(Request $request)
    {
        $userId = $request->user()?->id;

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('admin.logout', [
            'user_id' => $userId,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('admin.login')
            ->with('success', 'You have been logged out successfully.');
    }
}
