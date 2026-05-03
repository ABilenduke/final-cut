<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        $locations = Location::orderBy('name')->get();

        return $this->successResponse(LocationResource::collection($locations));
    }

    public function show(Location $location): JsonResponse
    {
        return $this->successResponse(new LocationResource($location));
    }
}
