<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\JobOpening;
use App\Observers\JobOpeningObserver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class JobOpeningController extends Controller
{
    /**
     * GET /api/job-openings
     *
     * Published openings in display order. `type` carries the display
     * string ('Full-time' / 'Part-time') the careers page has always
     * rendered; versioned-cache pattern as elsewhere.
     */
    public function index(): JsonResponse
    {
        $version = (int) Cache::get(JobOpeningObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "job_openings_public:v{$version}";

        $data = Cache::remember($cacheKey, 300, function () {
            return JobOpening::active()
                ->orderBy('display_order')
                ->orderBy('id')
                ->get()
                ->map(fn (JobOpening $opening): array => [
                    'id' => $opening->id,
                    'title' => $opening->title,
                    'department' => $opening->department,
                    'type' => $opening->employment_type,
                    'description' => $opening->description,
                ])
                ->all();
        });

        return $this->successResponse($data);
    }
}
