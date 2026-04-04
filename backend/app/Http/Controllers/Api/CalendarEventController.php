<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->successResponse([]);
    }

    public function show(string $slug): JsonResponse
    {
        return $this->successResponse([]);
    }
}
