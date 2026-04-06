<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\UserProfileResource;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    private const BOOKING_RELATIONS = ['showtime.movie', 'showtime.auditorium', 'seats.seat', 'foodItems'];

    public function __construct(private LoyaltyService $loyaltyService) {}

    public function profile(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserProfileResource($request->user())
        );
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Remove fields that shouldn't be set directly on the model
        unset($validated['current_password'], $validated['password_confirmation']);

        // Password is auto-hashed by the User model's 'hashed' cast
        $user->update($validated);

        return $this->successResponse(
            new UserProfileResource($user->refresh())
        );
    }

    public function orders(Request $request): JsonResponse
    {
        $paginator = $request->user()->bookings()
            ->with(self::BOOKING_RELATIONS)
            ->latest()
            ->paginate(min(max($request->integer('limit', 10), 1), 50));

        return $this->paginatedResponse($paginator, BookingResource::class);
    }

    public function bookings(Request $request): JsonResponse
    {
        $bookings = $request->user()->bookings()
            ->with(self::BOOKING_RELATIONS)
            ->where('status', BookingStatus::Confirmed)
            ->whereHas('showtime', fn ($q) => $q->where('start_time', '>', now()))
            ->join('showtimes', 'showtimes.id', '=', 'bookings.showtime_id')
            ->orderBy('showtimes.start_time', 'asc')
            ->select('bookings.*')
            ->get();

        return $this->successResponse(BookingResource::collection($bookings));
    }

    public function loyalty(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse([
            'points' => $this->loyaltyService->getPoints($user),
            'tier' => $this->loyaltyService->getTier($user),
            'premierExpiry' => $user->premier_expiry?->toIso8601String(),
            'history' => $this->loyaltyService->getHistory($user),
        ]);
    }
}
