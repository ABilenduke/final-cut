<?php

namespace App\Models;

use Database\Factories\FaqItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Admin-managed FAQ entry (admin-v2 Plan 13 — replaces the static
 * `frontend/app/data/faq.ts`). Category is a free string so editors can
 * introduce new sections without a code change; the public API groups by it.
 *
 * @property string $id
 * @property string $category
 * @property string $question
 * @property string $answer
 * @property int $display_order
 * @property Carbon|null $published_at
 */
#[Fillable(['category', 'question', 'answer', 'display_order', 'published_at'])]
class FaqItem extends Model
{
    /** @use HasFactory<FaqItemFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
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
     * @param  Builder<FaqItem>  $query
     * @return Builder<FaqItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
