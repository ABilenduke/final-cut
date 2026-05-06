<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = validator($request->query(), [
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'type' => ['nullable', 'string'],
            'accessibility' => ['nullable', 'string'],
        ])->validate();

        $locationSlug = $this->resolveOptionalLocationSlug($request);
        if ($locationSlug instanceof JsonResponse) {
            return $locationSlug;
        }

        $month = isset($validated['month']) ? (int) $validated['month'] : (int) now()->format('m');
        $year = isset($validated['year']) ? (int) $validated['year'] : (int) now()->format('Y');

        $monthDate = Carbon::createFromDate($year, $month, 1);
        $startOfMonth = $monthDate->copy()->startOfMonth()->toDateString();
        $endOfMonth = $monthDate->copy()->endOfMonth()->toDateString();

        $query = CalendarEvent::query()
            ->whereBetween('date', [$startOfMonth, $endOfMonth]);

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (! empty($validated['accessibility'])) {
            $tags = explode(',', $validated['accessibility']);
            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('accessibility_tags', trim($tag));
                }
            });
        }

        // When ?location= is provided, include:
        //   - events scoped to that location (location_id matches)
        //   - venue-agnostic events (location_id IS NULL) — these appear everywhere
        if (! empty($locationSlug)) {
            $query->where(function ($q) use ($locationSlug) {
                $q->whereNull('location_id')
                    ->orWhereHas('location', fn ($q) => $q->where('slug', $locationSlug));
            });
        }

        $events = $query->orderBy('date')->orderBy('start_time')->get();

        return $this->successResponse(CalendarEventResource::collection($events));
    }

    public function show(string $slug): JsonResponse
    {
        $event = CalendarEvent::where('slug', $slug)->first();

        if (! $event) {
            return $this->errorResponse([['message' => 'Calendar event not found']], 404);
        }

        return $this->successResponse(new CalendarEventResource($event));
    }
}
