<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\ProductReview;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

/**
 * Sidebar "new work" badges — counts clear when an admin opens that section.
 *
 * Orders / Reviews use a seen watermark (queue items still exist; the badge
 * only resurfaces for items created after the last visit). Contact messages
 * flip is_read so the inbox matches the zeroed badge.
 */
class NavBadge
{
    public const SECTIONS = ['orders', 'reviews', 'contact'];

    public static function unseenOrders(): int
    {
        return (int) Cache::remember('chrome.orders.unseen_count', 30, function () {
            $query = Order::query()->where('status', 'pending');
            $seenAt = self::seenAt('orders');

            if ($seenAt) {
                $query->where('created_at', '>', $seenAt);
            }

            return $query->count();
        });
    }

    public static function unseenReviews(): int
    {
        return (int) Cache::remember('chrome.reviews.pending_count', 30, function () {
            $query = ProductReview::pending();
            $seenAt = self::seenAt('reviews');

            if ($seenAt) {
                $query->where('created_at', '>', $seenAt);
            }

            return $query->count();
        });
    }

    public static function unseenContact(): int
    {
        return (int) Cache::remember(
            'chrome.contact_messages.unread_count',
            30,
            fn () => ContactMessage::unread()->count()
        );
    }

    /**
     * Persist that the admin has seen this section.
     *
     * @return int The count that was showing before clear (for the zero animation).
     */
    public static function markSeen(string $section): int
    {
        if (! in_array($section, self::SECTIONS, true)) {
            return 0;
        }

        $count = match ($section) {
            'orders' => self::unseenOrders(),
            'reviews' => self::unseenReviews(),
            'contact' => self::unseenContact(),
        };

        if ($count < 1) {
            return 0;
        }

        if ($section === 'contact') {
            // Mass update skips model events — bust chrome cache explicitly.
            ContactMessage::unread()->update(['is_read' => true]);
        } else {
            Cache::forever(self::seenKey($section), now()->toIso8601String());
        }

        AppServiceProvider::forgetChromeCache();

        return $count;
    }

    public static function seenAt(string $section): ?Carbon
    {
        $raw = Cache::get(self::seenKey($section));

        if (! $raw) {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function seenKey(string $section): string
    {
        return "chrome.nav.seen.{$section}";
    }
}
