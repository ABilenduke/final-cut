<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'row' => $this->row,
            'number' => $this->number,
            'label' => $this->label,
            'type' => $this->type->value,
            'status' => $this->computed_status ?? 'available',
            'price' => $this->computed_price ?? 0,
        ];
    }
}
