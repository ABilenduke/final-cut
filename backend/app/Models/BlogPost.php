<?php

namespace App\Models;

use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Admin-authored blog post (admin-v2 Plan 12 — replaces the static
 * `frontend/app/data/blog.ts`, which CLAUDE.md earmarked for this since v1).
 * Draft → publish via `published_at`; no display windows — posts don't
 * expire. `body` is plain text with blank-line paragraph breaks, matching
 * the public page's split-on-\n\n rendering.
 *
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property string $excerpt
 * @property string $author_name
 * @property string|null $image_url
 * @property string $body
 * @property Carbon|null $published_at
 */
#[Fillable([
    'title', 'slug', 'excerpt', 'author_name', 'image_url', 'body', 'published_at',
])]
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function displayStatus(): string
    {
        if ($this->published_at === null) {
            return 'draft';
        }

        return $this->published_at->isFuture() ? 'scheduled' : 'live';
    }

    /**
     * Scope: posts currently visible to the public.
     *
     * @param  Builder<BlogPost>  $query
     * @return Builder<BlogPost>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
