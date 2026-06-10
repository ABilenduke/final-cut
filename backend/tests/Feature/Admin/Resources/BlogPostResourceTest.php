<?php

use App\Filament\Resources\BlogPostResource;
use App\Filament\Resources\BlogPostResource\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPostResource\Pages\ListBlogPosts;
use App\Models\BlogPost;
use Database\Seeders\BlogPostSeeder;
use Livewire\Livewire;

use function Pest\Laravel\getJson;

// ── Public API ──────────────────────────────────────────────────────────────

test('the list api returns published posts newest-first without bodies', function (): void {
    BlogPost::factory()->create([
        'title' => 'Older', 'slug' => 'older', 'published_at' => now()->subDays(3),
    ]);
    BlogPost::factory()->create([
        'title' => 'Newest', 'slug' => 'newest', 'published_at' => now()->subDay(),
    ]);
    BlogPost::factory()->create(['title' => 'Draft', 'slug' => 'draft', 'published_at' => null]);
    BlogPost::factory()->create([
        'title' => 'Scheduled', 'slug' => 'scheduled', 'published_at' => now()->addDay(),
    ]);

    getJson('/api/blog-posts')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.slug', 'newest')
        ->assertJsonPath('data.1.slug', 'older')
        ->assertJsonMissingPath('data.0.body');
});

test('the detail api returns the body and 404s drafts and unknown slugs', function (): void {
    $post = BlogPost::factory()->published()->create([
        'slug' => 'how-we-built-it',
        'body' => "First paragraph.\n\nSecond paragraph.",
    ]);
    BlogPost::factory()->create(['slug' => 'still-draft', 'published_at' => null]);

    getJson('/api/blog-posts/how-we-built-it')
        ->assertOk()
        ->assertJsonPath('data.slug', 'how-we-built-it')
        ->assertJsonPath('data.body', "First paragraph.\n\nSecond paragraph.")
        ->assertJsonPath('data.date', $post->published_at->toDateString());

    getJson('/api/blog-posts/still-draft')->assertNotFound();
    getJson('/api/blog-posts/never-existed')->assertNotFound();
});

test('the caches bust on save for both list and detail', function (): void {
    $post = BlogPost::factory()->published()->create(['slug' => 'living-post', 'title' => 'Before']);

    getJson('/api/blog-posts')->assertJsonPath('data.0.title', 'Before');
    getJson('/api/blog-posts/living-post')->assertJsonPath('data.title', 'Before');

    $post->update(['title' => 'After']);

    getJson('/api/blog-posts')->assertJsonPath('data.0.title', 'After');
    getJson('/api/blog-posts/living-post')->assertJsonPath('data.title', 'After');
});

// ── Admin resource ──────────────────────────────────────────────────────────

test('a manager can draft a post with an auto-suggested slug and publish it', function (): void {
    $this->actingAsManager();

    Livewire::test(CreateBlogPost::class)
        ->set('data.title', 'A Night at the Projection Booth')
        ->set('data.slug', 'a-night-at-the-projection-booth')
        ->set('data.author_name', 'Final Cut Staff')
        ->set('data.excerpt', 'What actually happens behind the porthole window.')
        ->set('data.body', "Paragraph one.\n\nParagraph two.")
        ->call('create')
        ->assertHasNoFormErrors();

    $post = BlogPost::where('slug', 'a-night-at-the-projection-booth')->first();
    expect($post)->not->toBeNull()
        ->and($post->displayStatus())->toBe('draft');

    Livewire::test(ListBlogPosts::class)
        ->callTableAction('publish', $post)
        ->assertHasNoTableActionErrors();

    expect($post->refresh()->displayStatus())->toBe('live');
});

test('ops cannot access the resource', function (): void {
    $this->actingAsOps();
    expect(BlogPostResource::canViewAny())->toBeFalse();

    $this->actingAsManager();
    expect(BlogPostResource::canViewAny())->toBeTrue();
});

// ── Seeder parity ───────────────────────────────────────────────────────────

test('the seeder imports the three legacy static posts as live', function (): void {
    $this->seed(BlogPostSeeder::class);

    expect(BlogPost::count())->toBe(3);

    getJson('/api/blog-posts')->assertOk()->assertJsonCount(3, 'data');
    getJson('/api/blog-posts/grand-opening-announcement')
        ->assertOk()
        ->assertJsonPath('data.author', 'Final Cut Staff');
});
