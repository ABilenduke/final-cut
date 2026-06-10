<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TickerItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for the public ticker-items endpoint (GET /api/ticker-items).
 * Only public fields — ordering is implicit in array position.
 *
 * @mixin TickerItem
 */
class TickerItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'text' => $this->text,
            'href' => $this->href,
        ];
    }
}
