<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\FaqItem;
use App\Observers\FaqItemObserver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class FaqController extends Controller
{
    /**
     * GET /api/faq
     *
     * Published FAQ entries grouped by category — the same
     * `[{category, items: [{question, answer}]}]` shape the static
     * `data/faq.ts` exported, so the page contract is unchanged.
     * Categories appear in the order of their first item (by
     * display_order); versioned-cache pattern as elsewhere.
     */
    public function index(): JsonResponse
    {
        $version = (int) Cache::get(FaqItemObserver::CACHE_VERSION_KEY, 0);
        $cacheKey = "faq_items_public:v{$version}";

        $data = Cache::remember($cacheKey, 300, function () {
            $grouped = [];

            $items = FaqItem::active()
                ->orderBy('display_order')
                ->orderBy('id')
                ->get();

            foreach ($items as $item) {
                $grouped[$item->category] ??= [];
                $grouped[$item->category][] = [
                    'question' => $item->question,
                    'answer' => $item->answer,
                ];
            }

            return array_map(
                fn (string $category) => ['category' => $category, 'items' => $grouped[$category]],
                array_keys($grouped),
            );
        });

        return $this->successResponse($data);
    }
}
