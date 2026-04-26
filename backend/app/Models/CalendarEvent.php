<?php

namespace App\Models;

use App\Enums\CalendarEventType;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
