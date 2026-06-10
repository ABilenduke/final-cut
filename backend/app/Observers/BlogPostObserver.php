<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BlogPost;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates the blog public caches (list + per-slug) when a post is
 * created, updated, or deleted. Same versioned-key pattern as
 * FeaturedSlideObserver / TickerItemObserver.
 */
class BlogPostObserver
{
    public const string CACHE_VERSION_KEY = 'blog_posts_public_version';

    public static function bumpVersion(): void
    {
        // Database cache store returns false from increment() on a missing
        // key — seed it so the first bump never silently no-ops.
        if (Cache::increment(self::CACHE_VERSION_KEY) === false) {
            Cache::forever(self::CACHE_VERSION_KEY, 1);
        }
    }

    public function saved(BlogPost $post): void
    {
        if ($post->wasRecentlyCreated || $post->wasChanged()) {
            self::bumpVersion();
        }
    }

    public function deleted(BlogPost $post): void
    {
        self::bumpVersion();
    }
}
