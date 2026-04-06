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
        $month = $request->integer('month', (int) now()->format('m'));
        $year = $request->integer('year', (int) now()->format('Y'));

        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $query = CalendarEvent::query()
            ->whereBetween('date', [$startOfMonth, $endOfMonth]);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('accessibility')) {
            $tags = explode(',', $request->input('accessibility'));
            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('accessibility_tags', trim($tag));
                }
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
