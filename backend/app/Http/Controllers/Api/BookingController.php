<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function show(string $id): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function confirm(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }
}
