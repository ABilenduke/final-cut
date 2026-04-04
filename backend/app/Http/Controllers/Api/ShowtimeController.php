<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\SeatType;
use App\Http\Resources\AuditoriumResource;
use App\Http\Resources\SeatResource;
use App\Http\Resources\ShowtimeResource;
use App\Models\BookingSeat;
use App\Models\Showtime;
use Illuminate\Http\JsonResponse;

class ShowtimeController extends Controller
{
    public function show(string $id): JsonResponse
    {
        $showtime = Showtime::with(['movie', 'auditorium.seats'])->find($id);

        if (! $showtime) {
            return $this->errorResponse(['message' => 'Showtime not found'], 404);
        }

        // Single query: get all booked seat IDs for this showtime (confirmed bookings only)
        $takenSeatIds = BookingSeat::where('showtime_id', $showtime->id)
            ->whereHas('booking', fn ($q) => $q->where('status', BookingStatus::Confirmed))
            ->pluck('seat_id')
            ->all();

        // Compute status and price for each seat in-memory
        $showtime->auditorium->seats->each(function ($seat) use ($showtime, $takenSeatIds) {
            $seat->computed_status = in_array($seat->id, $takenSeatIds) ? 'taken' : 'available';
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
