<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'imageUrl' => $this->image_path
                ? Storage::disk('public')->url($this->image_path)
                : null,
            'slug' => $this->slug,
            'ticketUrl' => $this->ticket_url,
            'loyaltyOnly' => $this->loyalty_only,
            'accessibilityTags' => $this->accessibility_tags ?? [],
        ];
    }
}
