<?php

use App\Models\Banner;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('exposes only published pages by slug', function () {
    Page::create([
        'id' => (string) Str::uuid(),
        'slug' => 'about',
        'title' => 'About Dokan Ward',
        'content' => '<p>Our story</p>',
        'published' => true,
        'position' => 0,
    ]);
    Page::create([
        'id' => (string) Str::uuid(),
        'slug' => 'draft-page',
        'title' => 'Draft',
        'content' => '<p>Hidden</p>',
        'published' => false,
        'position' => 1,
    ]);

    $this->getJson('/api/pages/about')
        ->assertOk()
        ->assertJsonPath('slug', 'about')
        ->assertJsonPath('title', 'About Dokan Ward');

    $this->getJson('/api/pages/draft-page')->assertNotFound();

    $this->getJson('/api/pages')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'about'])
        ->assertJsonMissing(['slug' => 'draft-page']);
});

it('exposes only live banners ordered by position', function () {
    Banner::create([
        'id' => (string) Str::uuid(),
        'title' => 'Second',
        'subtitle' => 'B',
        'button_text' => 'Shop',
        'button_url' => '/collections/all',
        'image_url' => '/images/b.jpg',
        'is_active' => true,
        'position' => 2,
    ]);
    Banner::create([
        'id' => (string) Str::uuid(),
        'title' => 'First',
        'subtitle' => 'A',
        'button_text' => 'Shop',
        'button_url' => '/collections/all',
        'image_url' => '/images/a.jpg',
        'is_active' => true,
        'position' => 1,
    ]);
    Banner::create([
        'id' => (string) Str::uuid(),
        'title' => 'Off',
        'is_active' => false,
        'position' => 0,
    ]);

    $this->getJson('/api/banners')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'First')
        ->assertJsonPath('data.1.title', 'Second');
});
