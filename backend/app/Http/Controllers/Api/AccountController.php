<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function updateProfile(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function orders(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function bookings(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function loyalty(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }
}
