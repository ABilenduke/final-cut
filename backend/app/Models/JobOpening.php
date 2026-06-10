<?php

namespace App\Models;

use Database\Factories\JobOpeningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Admin-managed job opening (admin-v2 Plan 13 — replaces the hardcoded
 * array in the careers page).
 *
 * @property string $id
 * @property string $title
 * @property string $department
 * @property string $employment_type
 * @property string $description
 * @property int $display_order
 * @property Carbon|null $published_at
 */
#[Fillable(['title', 'department', 'employment_type', 'description', 'display_order', 'published_at'])]
class JobOpening extends Model
{
    /** @use HasFactory<JobOpeningFactory> */
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
     * @param  Builder<JobOpening>  $query
     * @return Builder<JobOpening>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
