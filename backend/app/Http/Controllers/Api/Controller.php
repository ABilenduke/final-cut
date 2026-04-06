<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

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
