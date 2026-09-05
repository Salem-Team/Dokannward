<?php

use App\Support\PublicUrl;
use Carbon\Carbon;

it('returns null for empty media urls', function () {
    expect(PublicUrl::versioned(null))->toBeNull()
        ->and(PublicUrl::versioned(''))->toBeNull()
        ->and(PublicUrl::versioned('   '))->toBeNull();
});

it('appends a cache-busting version query to media urls', function () {
    $at = Carbon::createFromTimestamp(1_700_000_000, 'UTC');

    expect(PublicUrl::versioned('https://dokannward.com/storage/categories/a.jpg', $at))
        ->toBe('https://dokannward.com/storage/categories/a.jpg?v=1700000000');

    expect(PublicUrl::versioned('https://dokannward.com/storage/categories/a.jpg?x=1', $at))
        ->toBe('https://dokannward.com/storage/categories/a.jpg?x=1&v=1700000000');
});

it('leaves urls unchanged when no version is provided', function () {
    expect(PublicUrl::versioned('https://dokannward.com/storage/categories/a.jpg'))
        ->toBe('https://dokannward.com/storage/categories/a.jpg');
});
