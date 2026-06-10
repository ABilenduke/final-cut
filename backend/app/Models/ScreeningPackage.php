<?php

namespace App\Models;

use Database\Factories\ScreeningPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Admin-managed private-screening package (admin-v2 Plan 14 — replaces the
 * hardcoded array in the private-screenings page).
 *
 * @property string $id
 * @property string $name
 * @property string $description
 * @property int $starting_price
 * @property list<string> $features
 * @property int $display_order
 * @property Carbon|null $published_at
 */
#[Fillable(['name', 'description', 'starting_price', 'features', 'display_order', 'published_at'])]
class ScreeningPackage extends Model
{
    /** @use HasFactory<ScreeningPackageFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'starting_price' => 'integer',
            'features' => 'array',
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
     * @param  Builder<ScreeningPackage>  $query
     * @return Builder<ScreeningPackage>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
