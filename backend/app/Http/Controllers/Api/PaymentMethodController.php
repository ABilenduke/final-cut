<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function store(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function destroy(string $id): JsonResponse
    {
        return $this->notImplementedResponse();
    }
}
