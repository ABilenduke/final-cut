<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function login(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function me(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }
}
