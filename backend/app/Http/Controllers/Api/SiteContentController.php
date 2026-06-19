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

    /**
     * GET /api/site-content/private-screenings
     *
     * The private-screenings page intro copy — title + lead paragraph
     * (admin-v6 G3). The packages themselves are admin-managed via
     * ScreeningPackageResource; this is just the editorial header. Null until
     * an admin saves the form — the frontend renders its built-in copy as the
     * fallback. Versioned cache as elsewhere.
     */
    public function privateScreenings(): JsonResponse
    {
        $version = (int) Cache::get(SiteSettingObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "site_content_private_screenings:v{$version}";

        $data = Cache::remember($cacheKey, 300, function () {
            return [
                'privateScreenings' => $this->settings->get(SiteSettingsService::KEY_PRIVATE_SCREENINGS),
            ];
        });

        return $this->successResponse($data);
    }

    /**
     * GET /api/site-content/accessibility
     *
     * The accessibility-page prose — intro + the six section paragraphs
     * (admin-v6 G4). The section headings, calendar links, and contact block
     * stay structural; only the descriptive copy is editable. Null until an
     * admin saves the form — the frontend renders its built-in copy as the
     * fallback. Versioned cache as elsewhere.
     */
    public function accessibility(): JsonResponse
    {
        $version = (int) Cache::get(SiteSettingObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "site_content_accessibility:v{$version}";

        $data = Cache::remember($cacheKey, 300, function () {
            return [
                'accessibility' => $this->settings->get(SiteSettingsService::KEY_ACCESSIBILITY_STATEMENT),
            ];
        });

        return $this->successResponse($data);
    }

    /**
     * GET /api/site-content/navigation
     *
     * Admin-managed header + footer navigation items (admin-v6 G1). Each list
     * is null until an admin saves the Navigation form — the frontend falls
     * back to its built-in nav, so the shell never renders empty. Versioned
     * cache as elsewhere.
     */
    public function navigation(): JsonResponse
    {
        $version = (int) Cache::get(SiteSettingObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "site_content_navigation:v{$version}";

        $data = Cache::remember($cacheKey, 300, function () {
            $saved = $this->settings->get(SiteSettingsService::KEY_NAVIGATION);

            return [
                'header' => is_array($saved) ? ($saved['header'] ?? null) : null,
                'footer' => is_array($saved) ? ($saved['footer'] ?? null) : null,
            ];
        });

        return $this->successResponse($data);
    }

    /**
     * GET /api/site-content/gift-cards
     *
     * Gift-cards page masthead copy — { eyebrow, lede } (admin-v6 G8). The
     * stylized <h1> title stays structural (it carries brand typography); only
     * the eyebrow kicker and the lede paragraph are editable. Null until an
     * admin saves the form — the frontend renders its built-in copy as the
     * fallback. Versioned cache as elsewhere.
     */
    public function giftCards(): JsonResponse
    {
        $version = (int) Cache::get(SiteSettingObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "site_content_gift_cards:v{$version}";

        $data = Cache::remember($cacheKey, 300, function () {
            return [
                'giftCards' => $this->settings->get(SiteSettingsService::KEY_GIFT_CARDS_EDITORIAL),
            ];
        });

        return $this->successResponse($data);
    }
}
