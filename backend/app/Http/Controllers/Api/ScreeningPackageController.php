<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\ScreeningPackage;
use App\Observers\ScreeningPackageObserver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ScreeningPackageController extends Controller
{
    /**
     * GET /api/screening-packages
     *
     * Published packages in display order. `startingPrice` is cents — the
     * page's existing PackageCard contract. Versioned cache as elsewhere.
     */
    public function index(): JsonResponse
    {
        $version = (int) Cache::get(ScreeningPackageObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "screening_packages_public:v{$version}";

        $data = Cache::remember($cacheKey, 300, function () {
            return ScreeningPackage::active()
                ->orderBy('display_order')
                ->orderBy('id')
                ->get()
                ->map(fn (ScreeningPackage $package): array => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'startingPrice' => $package->starting_price,
                    'features' => $package->features,
                ])
                ->all();
        });

        return $this->successResponse($data);
    }
}
