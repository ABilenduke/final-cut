<?php

namespace App\Models;

use Database\Factories\TickerItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Admin-curated Neural Ticker entry (admin-v2 Plan 11 — replaces the
 * hardcoded array in the frontend's default layout). Publish/window
 * semantics are identical to FeaturedSlide: visible when published_at is
 * set and past, and the optional starts_at/ends_at window is open.
 *
 * @property string $id
 * @property string $label
 * @property string $text
 * @property string|null $href
 * @property int $display_order
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $published_at
 */
#[Fillable([
    'label', 'text', 'href',
    'display_order',
    'starts_at', 'ends_at', 'published_at',
])]
class TickerItem extends Model
{
    /** @use HasFactory<TickerItemFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /** Admin-facing status — same priority order as FeaturedSlide::displayStatus(). */
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
     * Scope: items currently visible to the public.
     *
     * @param  Builder<TickerItem>  $query
     * @return Builder<TickerItem>
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
