<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ContactRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public function store(ContactRequest $request): JsonResponse
    {
        Log::info('Contact form submission', [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'subject' => $request->validated('subject'),
            'message' => $request->validated('message'),
        ]);

        return $this->successResponse(['success' => true]);
    }
}
