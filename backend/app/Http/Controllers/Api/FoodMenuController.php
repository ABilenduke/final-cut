<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FoodMenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->successResponse([]);
    }
}
