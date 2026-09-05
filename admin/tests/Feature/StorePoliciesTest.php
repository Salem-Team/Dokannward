<?php

use App\Models\Page;
use App\Models\User;
use App\Support\StorePolicies;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('ensures every canonical policy exists once and never overwrites saved body', function () {
    $first = StorePolicies::ensure('terms-of-service');
    expect($first->slug)->toBe('terms-of-service');
    expect($first->published)->toBeTrue();
    expect(trim(strip_tags((string) $first->content)))->not->toBe('');

    $custom = '<p>Custom terms body from admin.</p>';
    $first->update(['content' => $custom, 'title' => 'Custom Terms']);

    $again = StorePolicies::ensure('terms-of-service');
    expect($again->id)->toBe($first->id);
    expect($again->content)->toBe($custom);
    expect($again->title)->toBe('Custom Terms');

    $all = StorePolicies::ensureAll();
    expect(array_keys($all))->toBe(StorePolicies::slugs());
});

it('exposes published policy HTML from the pages API exactly as stored', function () {
    $body = '<p>Live policy unique marker 9f3a.</p><h2>Section</h2>';
    $page = StorePolicies::ensure('refund-policy');
    $page->update([
        'title' => 'Refund & Returns',
        'content' => $body,
        'published' => true,
    ]);

    $this->getJson('/api/pages/refund-policy')
        ->assertOk()
        ->assertJsonPath('slug', 'refund-policy')
        ->assertJsonPath('content', $body);
});

it('hides unpublished policies from the public API', function () {
    $page = StorePolicies::ensure('shipping-policy');
    $page->update([
        'content' => '<p>Draft only</p>',
        'published' => false,
    ]);

    $this->getJson('/api/pages/shipping-policy')->assertNotFound();
});

it('rejects publishing a policy with empty body', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    StorePolicies::ensure('privacy-policy');

    $this->actingAs($admin)
        ->put(route('admin.policies.update', 'privacy-policy'), [
            'title' => 'Privacy Policy',
            'content' => '<p><br></p>',
            'published' => '1',
        ])
        ->assertSessionHasErrors('content');
});

it('redirects legal policy edits away from the generic pages editor', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $page = StorePolicies::ensure('terms-of-service');

    $this->actingAs($admin)
        ->get(route('admin.pages.edit', $page->id))
        ->assertRedirect(route('admin.policies.edit', 'terms-of-service'));
});

it('refuses deleting a legal policy from the pages CRUD', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $page = StorePolicies::ensure('terms-of-service');

    $this->actingAs($admin)
        ->delete(route('admin.pages.destroy', $page->id))
        ->assertRedirect(route('admin.policies.index'));

    expect(Page::where('slug', 'terms-of-service')->exists())->toBeTrue();
});
