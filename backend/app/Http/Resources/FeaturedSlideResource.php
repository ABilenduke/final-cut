<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\AssetUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for the public featured-slides endpoint (GET /api/featured-slides).
 *
 * Deliberately enumerates only the public fields. Internal fields
 * (display_order, published_at, starts_at, ends_at, created_at, updated_at)
 * are excluded — the sort order is implicit in the array position.
 */
class FeaturedSlideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'headline' => $this->headline,
            'sub_headline' => $this->sub_headline,
            'image_url' => AssetUrl::resolve($this->image_url),
            'cta_label' => $this->cta_label,
            'cta_href' => $this->cta_href,
        ];
    }
}
