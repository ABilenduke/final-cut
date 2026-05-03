<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class Controller extends BaseController
{
    protected function successResponse(mixed $data, ?array $meta = null, int $status = 200): JsonResponse
    {
        $response = ['data' => $data];

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status);
    }

    protected function errorResponse(array $errors, int $status = 400): JsonResponse
    {
        return response()->json(['errors' => $errors], $status);
    }

    protected function notImplementedResponse(): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    /**
     * Resolve and validate an optional `?location={slug}` query param.
     *
     * Returns the slug string when present and the venue exists, null when
     * absent or empty, or a 422 JsonResponse when the slug doesn't match a
     * known venue. Callers should `return` the JsonResponse case immediately:
     *
     *   $loc = $this->resolveOptionalLocationSlug($request);
     *   if ($loc instanceof JsonResponse) return $loc;
     *   // $loc is now ?string
     */
    protected function resolveOptionalLocationSlug(Request $request): JsonResponse|string|null
    {
        $slug = $request->query('location');
        if ($slug === null || $slug === '') {
            return null;
        }

        if (! DB::table('locations')->where('slug', $slug)->exists()) {
            return $this->errorResponse(
                [['field' => 'location', 'message' => 'The selected location is invalid.']],
                422
            );
        }

        return (string) $slug;
    }

    protected function paginatedResponse(LengthAwarePaginator $paginator, ?string $resourceClass = null): JsonResponse
    {
        $items = $resourceClass
            ? $resourceClass::collection($paginator->items())
            : $paginator->items();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ],
        ]);
    }
}
