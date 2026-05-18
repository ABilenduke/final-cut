<?php

namespace App\Http\Resources;

use App\Models\CalendarEvent;
use App\Support\AssetUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CalendarEvent
 */
class CalendarEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'date' => $this->date->toDateString(),
            'startTime' => $this->start_time?->toIso8601String(),
            'endTime' => $this->end_time?->toIso8601String(),
            'description' => $this->description,
            'movieSlug' => $this->movie_slug,
            // Synthetic showtime entries (see ShowtimeCalendarProjector) preset
            // `image_url` to a full URL; stored calendar_events rows carry only
            // `image_path`. AssetUrl::resolve passes absolute URLs through and
            // resolves bare paths via Storage::disk('public')->url().
            'imageUrl' => AssetUrl::resolve($this->image_url ?? $this->image_path),
            'slug' => $this->slug,
            'ticketUrl' => $this->ticket_url,
            'loyaltyOnly' => $this->loyalty_only,
            'accessibilityTags' => $this->accessibility_tags ?? [],
            // Synthesized showtime entries (see ShowtimeCalendarProjector) carry
            // the per-screening tile data needed by the customer detail rail.
            // Stored calendar_events rows return null — the field is absent
            // from the underlying model attributes for those.
            'showtimes' => $this->showtimes ?? null,
        ];
    }
}
