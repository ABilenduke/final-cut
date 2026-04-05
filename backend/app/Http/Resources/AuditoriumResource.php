<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditoriumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $seatsByRow = $this->seats->sortBy([['row', 'asc'], ['number', 'asc']])->groupBy('row');

        $sections = $this->seats->pluck('type')->unique()->map(fn ($type) => [
            'name' => $type->value,
        ])->values()->toArray();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'totalSeats' => $this->total_seats,
            'rows' => $seatsByRow->map(fn ($seats, $row) => [
                'label' => $row,
                'seats' => SeatResource::collection($seats)->toArray($request),
                'section' => $seats->first()->type->value,
            ])->values()->toArray(),
            'sections' => $sections,
        ];
    }
}
