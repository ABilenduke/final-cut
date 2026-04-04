<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class ShowtimeController extends Controller
{
    public function show(string $id): JsonResponse
    {
        return $this->successResponse([]);
    }
}
