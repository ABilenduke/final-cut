<?php

namespace App\Models;

use Database\Factories\FeaturedSlideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Admin-curated home hero carousel slide.
 *
 * A slide is publicly visible when ALL of the following hold:
 *   - published_at IS NOT NULL and published_at <= now()  (not a draft, not future-published)
 *   - starts_at IS NULL OR starts_at <= now()             (window has opened, or no lower bound)
 *   - ends_at   IS NULL OR ends_at   >= now()             (window is still open, or no upper bound)
 *
 * Slides are sorted by display_order ASC for the public API.
 *
 * This is an admin-managed record. Image upload is handled by Filament FileUpload
 * against disk('public'); the model stores the resulting public URL in image_url.
 *
 * @property string $id
 * @property string $headline
 * @property string|null $sub_headline
 * @property string|null $image_url
 * @property string $cta_label
 * @property string $cta_href
 * @property int $display_order
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'headline', 'sub_headline',
    'image_url',
    'cta_label', 'cta_href',
    'display_order',
    'starts_at', 'ends_at', 'published_at',
])]
class FeaturedSlide extends Model
{
    /** @use HasFactory<FeaturedSlideFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Admin-facing status label synthesised from published_at / starts_at / ends_at.
     *
     * Priority order (first match wins):
     *   draft      — published_at is null
     *   expired    — ends_at is in the past (regardless of published_at)
     *   scheduled  — published_at is in the future, OR starts_at is in the future
     *   live       — published_at is set and in the past, window is open
     */
    public function displayStatus(): string
    {
        if ($this->published_at === null) {
            return 'draft';
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return 'expired';
        }

        if ($this->published_at->isFuture()) {
            return 'scheduled';
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return 'scheduled';
        }

        return 'live';
    }

    /**
     * Scope: slides that are currently visible to the public.
     *
     * Conditions:
     *   1. published_at is not null (not a draft)
     *   2. published_at <= now() (publish date has arrived)
     *   3. starts_at is null OR starts_at <= now() (display window has opened)
     *   4. ends_at is null OR ends_at >= now() (display window has not closed)
     *
     * @param  Builder<FeaturedSlide>  $query
     * @return Builder<FeaturedSlide>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $q) => $q
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now())
            )
            ->where(fn (Builder $q) => $q
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>=', now())
            );
    }
}
