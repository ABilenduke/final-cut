<?php

namespace App\Http\Controllers\Api;

use App\Enums\InquiryStatus;
use App\Http\Requests\RentalInquiryRequest;
use App\Models\RentalInquiry;
use Illuminate\Http\JsonResponse;

class RentalController extends Controller
{
    public function store(RentalInquiryRequest $request): JsonResponse
    {
        $inquiry = RentalInquiry::create([
            'event_type' => $request->validated('eventType'),
            'preferred_date' => $request->validated('preferredDate'),
            'guest_count' => $request->validated('guestCount'),
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'message' => $request->validated('message'),
            'status' => InquiryStatus::Pending,
        ]);

        return $this->successResponse([
            'success' => true,
            'inquiryId' => $inquiry->id,
        ], status: 201);
    }
}
