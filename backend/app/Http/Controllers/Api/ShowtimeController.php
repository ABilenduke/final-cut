<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Http\Resources\AuditoriumResource;
use App\Http\Resources\SeatResource;
use App\Http\Resources\ShowtimeResource;
use App\Models\BookingSeat;
use App\Models\Location;
use App\Models\Showtime;
use App\Services\SeatAvailabilityService;
use Illuminate\Http\JsonResponse;

class ShowtimeController extends Controller
{
    public function show(Location $location, string $id): JsonResponse
    {
        $showtime = Showtime::with(['movie', 'auditorium.seats.section'])
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

        // Compute status and price for each seat in-memory. Admin-flagged
        // `unavailable_at` seats render as 'taken' alongside booking
        // occupancy — the customer frontend already treats both identically
        // (non-selectable), and the booking path refuses reservation via
        // SeatAvailabilityService::checkAvailability (which also honours
        // `unavailable_at`).
        $showtime->auditorium->seats->each(function ($seat) use ($showtime, $takenSeatLookup) {
            $isOccupied = $takenSeatLookup->has($seat->id);
            $isOutOfService = $seat->unavailable_at !== null;

            $seat->computed_status = ($isOccupied || $isOutOfService) ? 'taken' : 'available';
            $seat->computed_price = SeatAvailabilityService::priceForSeat($showtime, $seat);
        });

        return $this->successResponse([
            'showtime' => new ShowtimeResource($showtime),
            'auditorium' => new AuditoriumResource($showtime->auditorium),
            'seats' => SeatResource::collection($showtime->auditorium->seats),
        ]);
    }
}
