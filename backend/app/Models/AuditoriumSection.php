<?php

namespace App\Models;

use Database\Factories\AuditoriumSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['auditorium_id', 'name', 'price_multiplier', 'display_order'])]
class AuditoriumSection extends Model
{
    /** @use HasFactory<AuditoriumSectionFactory> */
    use HasFactory, HasUuids;

    protected $table = 'auditorium_sections';

    protected function casts(): array
    {
        return [
            'price_multiplier' => 'decimal:2',
            'display_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Auditorium, $this> */
    public function auditorium(): BelongsTo
    {
        return $this->belongsTo(Auditorium::class);
    }

    /** @return HasMany<Seat, $this> */
    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class, 'section_id');
    }
}
