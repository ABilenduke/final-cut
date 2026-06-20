<?php

namespace App\Http\Controllers\Api;

use App\Enums\CalendarEventType;
use App\Http\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use App\Services\ShowtimeCalendarProjector;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CalendarEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $allowedAccessibilityTags = ['sensory_friendly', 'open_caption', 'audio_described'];

        $validated = validator($request->query(), [
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'type' => ['nullable', Rule::enum(CalendarEventType::class)],
            // CSV of accessibility tags. Empty segments (a trailing/leading
            // comma) are skipped to preserve the previous tolerance — the
            // downstream consume path trims each tag and matches nothing for ''.
            'accessibility' => ['nullable', 'string', function (string $attribute, mixed $value, callable $fail) use ($allowedAccessibilityTags): void {
                foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $tag) {
                    if (! in_array($tag, $allowedAccessibilityTags, true)) {
                        $fail("The {$attribute} field contains an unsupported tag: {$tag}.");
                    }
                }
            }],
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

        // Synthesize showtime-typed entries from the showtimes table on the fly.
        // Skipped when the type filter narrows to a non-showtime type.
        $includeShowtimes = empty($validated['type']) || $validated['type'] === CalendarEventType::Showtime->value;

        $synthetic = $includeShowtimes
            ? app(ShowtimeCalendarProjector::class)->eventsForRange(
                Carbon::parse($startOfMonth),
                Carbon::parse($endOfMonth),
                $locationSlug ?: null,
            )
            : collect();

        $merged = $events->concat($synthetic)
            ->sortBy(fn (CalendarEvent $event) => sprintf(
                '%s|%s',
                $event->date->format('Y-m-d'),
                $event->start_time?->format('Y-m-d H:i:s') ?? '0000-00-00 00:00:00',
            ))
            ->values();

        return $this->successResponse(CalendarEventResource::collection($merged));
    }

    public function show(string $slug): JsonResponse
    {
        // Eager-load the venue so CalendarEventResource can emit a structured
        // `location` for the customer-side Event JSON-LD. The index path
        // intentionally skips this — location is only needed on the detail page.
        $event = CalendarEvent::with('location')->where('slug', $slug)->first();

        if (! $event) {
            return $this->errorResponse([['message' => 'Calendar event not found']], 404);
        }

        return $this->successResponse(new CalendarEventResource($event));
    }
}
