<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Envelope respons tunggal untuk seluruh API supaya klien Flutter
 * hanya perlu satu jalur parsing.
 */
final class ApiResponse
{
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $body = ['success' => true, 'message' => $message];

        if ($data !== null) {
            $body['data'] = $data;
        }

        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    public static function error(
        string $message,
        int $status = 400,
        array $errors = [],
        ?string $code = null,
    ): JsonResponse {
        $body = ['success' => false, 'message' => $message];

        if ($code !== null) {
            $body['code'] = $code;
        }

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }

    /** Bungkus paginator jadi data[] + meta pagination. */
    public static function paginated(
        ResourceCollection|LengthAwarePaginator $paginated,
        ?string $message = null,
    ): JsonResponse {
        $paginator = $paginated instanceof ResourceCollection
            ? $paginated->resource
            : $paginated;

        $items = $paginated instanceof ResourceCollection
            ? $paginated->collection
            : $paginator->items();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }
}
