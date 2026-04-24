<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\SeatType;
use App\Http\Resources\AuditoriumResource;
use App\Http\Resources\SeatResource;
use App\Http\Resources\ShowtimeResource;
use App\Models\BookingSeat;
use App\Models\Location;
use App\Models\Showtime;
use Illuminate\Http\JsonResponse;

class ShowtimeController extends Controller
{
    public function show(Location $location, string $id): JsonResponse
    {
        $showtime = Showtime::with(['movie', 'auditorium.seats'])
            ->whereHas('auditorium', fn ($q) => $q->where('location_id', $location->id))
            ->find($id);

        if (! $showtime) {
            return $this->errorResponse(['message' => 'Showtime not found'], 404);
        }

        // Single query: all seat IDs taken by an occupying booking on this
        // showtime (Confirmed + Held + RefundPending — see BookingStatus).
        $takenSeatLookup = BookingSeat::where('showtime_id', $showtime->id)
            ->whereHas('booking', fn ($q) => $q->whereIn('status', BookingStatus::occupyingStatuses()))
            ->pluck('seat_id')
            ->flip();

        // Compute status and price for each seat in-memory
        $showtime->auditorium->seats->each(function ($seat) use ($showtime, $takenSeatLookup) {
            $seat->computed_status = $takenSeatLookup->has($seat->id) ? 'taken' : 'available';
            $seat->computed_price = match ($seat->type) {
                SeatType::Standard => $showtime->price_standard,
                SeatType::Premium => $showtime->price_premium,
                SeatType::Accessible => $showtime->price_accessible,
            };
        });

        return $this->successResponse([
            'showtime' => new ShowtimeResource($showtime),
            'auditorium' => new AuditoriumResource($showtime->auditorium),
            'seats' => SeatResource::collection($showtime->auditorium->seats),
        ]);
    }
}
