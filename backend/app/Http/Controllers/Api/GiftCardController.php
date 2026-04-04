<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    public function purchase(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function balance(Request $request): JsonResponse
    {
        return $this->notImplementedResponse();
    }
}
