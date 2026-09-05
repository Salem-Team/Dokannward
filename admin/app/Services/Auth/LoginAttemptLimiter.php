<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Progressive login lockout (OWASP Authentication Cheat Sheet).
 *
 * Tracks failed attempts by normalized email + IP. After the threshold,
 * further attempts are rejected until the lockout window expires.
 */
class LoginAttemptLimiter
{
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 900; // 15 minutes

    public function __construct(
        private readonly string $email,
        private readonly string $ip,
    ) {}

    public static function for(string $email, string $ip): self
    {
        return new self(strtolower(trim($email)), $ip);
    }

    public function tooManyAttempts(): bool
    {
        return $this->attempts() >= self::MAX_ATTEMPTS;
    }

    public function attempts(): int
    {
        return (int) Cache::get($this->key(), 0);
    }

    public function remainingSeconds(): int
    {
        $ttl = Cache::get($this->lockKey());

        if (is_int($ttl) || is_numeric($ttl)) {
            return max(0, (int) $ttl - time());
        }

        // Fallback: use the attempts-key TTL when store supports it.
        return self::LOCKOUT_SECONDS;
    }

    public function hit(): void
    {
        $attempts = $this->attempts() + 1;
        Cache::put($this->key(), $attempts, self::LOCKOUT_SECONDS);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $unlockAt = time() + self::LOCKOUT_SECONDS;
            Cache::put($this->lockKey(), $unlockAt, self::LOCKOUT_SECONDS);

            Log::warning('admin.login.lockout', [
                'email' => $this->email,
                'ip' => $this->ip,
                'attempts' => $attempts,
                'lockout_seconds' => self::LOCKOUT_SECONDS,
            ]);
        }
    }

    public function clear(): void
    {
        Cache::forget($this->key());
        Cache::forget($this->lockKey());
    }

    private function key(): string
    {
        return 'admin.login.attempts:'.hash('sha256', $this->email.'|'.$this->ip);
    }

    private function lockKey(): string
    {
        return 'admin.login.lock:'.hash('sha256', $this->email.'|'.$this->ip);
    }
}
