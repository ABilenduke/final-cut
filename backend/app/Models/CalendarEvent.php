<?php

namespace App\Models;

use App\Enums\CalendarEventType;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property CalendarEventType $type
 * @property string $title
 * @property Carbon $date
 * @property ?Carbon $start_time
 * @property ?Carbon $end_time
 * @property ?string $description
 * @property ?string $movie_slug
 * @property ?string $image_path
 * @property ?string $slug
 * @property ?string $ticket_url
 * @property bool $loyalty_only
 * @property ?array<int, string> $accessibility_tags
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'type', 'title', 'date', 'start_time', 'end_time', 'description',
    'movie_slug', 'image_path', 'slug', 'ticket_url', 'loyalty_only',
    'accessibility_tags',
])]
class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'type' => CalendarEventType::class,
            'date' => 'date',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'loyalty_only' => 'boolean',
            'accessibility_tags' => 'array',
        ];
    }
}
