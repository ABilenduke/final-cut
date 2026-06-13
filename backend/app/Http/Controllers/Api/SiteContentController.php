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

    /**
     * GET /api/site-content/contacts
     *
     * Site-wide contact details (footer line, support emails). `contacts`
     * is null until an admin saves the Site contacts form — the frontend
     * renders its built-in fallback values. Versioned cache as elsewhere.
     */
    public function contacts(): JsonResponse
    {
        $version = (int) Cache::get(SiteSettingObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "site_content_contacts:v{$version}";

        $data = Cache::remember($cacheKey, 300, function () {
            return [
                'contacts' => $this->settings->get(SiteSettingsService::KEY_SITE_CONTACTS),
            ];
        });

        return $this->successResponse($data);
    }

    /**
     * GET /api/site-content/careers
     *
     * The careers-page "why work here" benefits list (admin-v6 G5).
     * `benefits` is null until an admin saves the Careers content form — the
     * frontend renders its built-in list as the fallback. Versioned cache as
     * elsewhere.
     */
    public function careers(): JsonResponse
    {
        $version = (int) Cache::get(SiteSettingObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "site_content_careers:v{$version}";

        $data = Cache::remember($cacheKey, 300, function () {
            $saved = $this->settings->get(SiteSettingsService::KEY_CAREERS_BENEFITS);

            return [
                'benefits' => is_array($saved) ? ($saved['benefits'] ?? null) : null,
            ];
        });

        return $this->successResponse($data);
    }

    /**
     * GET /api/site-content/contact-info
     *
     * The contact-page "getting here" prose — By Car / By Transit /
     * Accessibility (admin-v6 G6). Brand-level (the contact page is brand-led;
     * per-venue detail lives on /locations/:slug). `contactInfo` is null until
     * an admin saves the Contact content form — the frontend renders its
     * built-in copy as the fallback. Versioned cache as elsewhere.
     */
    public function contactInfo(): JsonResponse
    {
        $version = (int) Cache::get(SiteSettingObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "site_content_contact_info:v{$version}";

        $data = Cache::remember($cacheKey, 300, function () {
            return [
                'contactInfo' => $this->settings->get(SiteSettingsService::KEY_CONTACT_INFO),
            ];
        });

        return $this->successResponse($data);
    }
}
