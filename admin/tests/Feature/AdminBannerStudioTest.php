<?php

use App\Models\Banner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function bannerAdmin(): User
{
    $user = User::factory()->admin()->create([
        'email' => 'banner-admin@example.com',
        'password' => 'SecurePass!234',
    ]);
    $user->forceFill(['is_admin' => true, 'is_active' => true, 'is_guest' => false])->save();

    return $user->fresh();
}

it('shows the banners atelier with website integration map', function () {
    $admin = bannerAdmin();

    Banner::create([
        'id' => (string) Str::uuid(),
        'title' => 'Primary plate',
        'subtitle' => 'Live on site',
        'button_text' => 'Shop now',
        'button_url' => '/collections/all',
        'image_url' => 'https://cdn.example.com/banner.jpg',
        'is_active' => true,
        'position' => 0,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.banners.index'))
        ->assertOk()
        ->assertSee('Website · Content')
        ->assertSee('Integration map')
        ->assertSee('/api/banners', false)
        ->assertSee('Homepage · Primary CTA')
        ->assertSee('Live · On site')
        ->assertSee('Primary plate');
});

it('renders the brand-studio create form with live homepage preview', function () {
    $admin = bannerAdmin();

    $this->actingAs($admin)
        ->get(route('admin.banners.create'))
        ->assertOk()
        ->assertSee('Homepage CTA plates')
        ->assertSee('banner-live-preview', false)
        ->assertSee('name="image_file"', false)
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('Live preview');
});

it('creates a banner with an uploaded cover and exposes it on the public API', function () {
    Storage::fake('public');
    $admin = bannerAdmin();
    $file = UploadedFile::fake()->image('hero.jpg', 1920, 1280);

    $this->actingAs($admin)
        ->post(route('admin.banners.store'), [
            'title' => 'Summer edit',
            'subtitle' => 'Light layers for warm days',
            'button_text' => 'Explore',
            'button_url' => '/collections/all',
            'image_source' => 'upload',
            'image_file' => $file,
            'position' => 0,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.banners.index'));

    $banner = Banner::query()->where('title', 'Summer edit')->first();
    expect($banner)->not->toBeNull();
    expect($banner->image_url)->toContain('/storage/banners/');
    expect($banner->liveStatus())->toBe('live');
    expect($banner->homepageSlotHint())->toBe('Homepage · Primary CTA');

    $this->getJson('/api/banners')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Summer edit')
        ->assertJsonPath('data.0.button_text', 'Explore');
});

it('creates a banner from an image URL and keeps inactive banners off the API', function () {
    $admin = bannerAdmin();

    $this->actingAs($admin)
        ->post(route('admin.banners.store'), [
            'title' => 'Draft plate',
            'subtitle' => 'Not yet',
            'button_text' => 'Soon',
            'button_url' => '/collections/all',
            'image_source' => 'url',
            'image_url' => 'https://cdn.example.com/draft.jpg',
            'position' => 1,
            'is_active' => '0',
        ])
        ->assertRedirect(route('admin.banners.index'));

    expect(Banner::query()->where('title', 'Draft plate')->value('image_url'))
        ->toBe('https://cdn.example.com/draft.jpg');

    $this->getJson('/api/banners')
        ->assertOk()
        ->assertJsonMissing(['title' => 'Draft plate']);
});

it('rejects invalid button links', function () {
    $admin = bannerAdmin();

    $this->actingAs($admin)
        ->from(route('admin.banners.create'))
        ->post(route('admin.banners.store'), [
            'title' => 'Bad link',
            'button_url' => 'javascript:alert(1)',
            'image_source' => 'url',
            'image_url' => 'https://cdn.example.com/ok.jpg',
            'position' => 0,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.banners.create'))
        ->assertSessionHasErrors('button_url');
});

it('seeds default homepage banners when the table is empty', function () {
    expect(\App\Models\Banner::query()->count())->toBe(0);

    $this->seed(\Database\Seeders\HomepageBannerSeeder::class);

    $this->getJson('/api/banners')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'curated essentials.')
        ->assertJsonPath('data.1.title', 'timeless design');

    // Idempotent — does not duplicate when run again.
    $this->seed(\Database\Seeders\HomepageBannerSeeder::class);
    expect(\App\Models\Banner::query()->count())->toBe(2);
});
