<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Observers\SiteSettingObserver;
use App\Services\SiteSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SiteContentController extends Controller
{
    public function __construct(
        private readonly SiteSettingsService $settings,
    ) {}

    /**
     * GET /api/site-content/home
     *
     * Editorial blobs for the home page. `membership` is null until an
     * admin saves the Home page content form — the frontend renders its
     * built-in copy as the fallback. Versioned cache as elsewhere.
     */
    public function home(): JsonResponse
    {
        $version = (int) Cache::get(SiteSettingObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "site_content_home:v{$version}";

        $data = Cache::remember($cacheKey, 300, function () {
            return [
                'membership' => $this->settings->get(SiteSettingsService::KEY_HOME_MEMBERSHIP),
            ];
        });

        return $this->successResponse($data);
    }
}
