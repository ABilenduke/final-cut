<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ContactRequest;
use App\Models\ContactSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public function store(ContactRequest $request): JsonResponse
    {
        // Persisted for the admin inbox (admin-v2 Plan 10); the log line
        // stays for ops-side grep parity with the pre-inbox era.
        ContactSubmission::create($request->validated());

        Log::info('Contact form submission', [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'subject' => $request->validated('subject'),
            'message' => $request->validated('message'),
        ]);

        return $this->successResponse(['success' => true]);
    }
}
