<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Penanda hidup untuk API.
 *
 * Dibuat sebagai controller, bukan closure di routes/api.php, supaya
 * swagger-php ikut membacanya: pemindaiannya hanya menjangkau folder app/.
 */
class HealthController extends Controller
{
    #[OA\Get(
        path: '/api/health',
        operationId: 'health',
        description: 'Endpoint publik tanpa autentikasi untuk memastikan API hidup dan jam servernya benar.',
        summary: 'Cek kesehatan API',
        tags: ['Referensi'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'API aktif',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'API aktif.'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'app', type: 'string', example: 'Transaksi Pekerjaan'),
                        new OA\Property(property: 'time', type: 'string', format: 'date-time'),
                    ], type: 'object'),
                ], type: 'object'),
            ),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
            'app' => config('app.name'),
            'time' => now()->toIso8601String(),
        ], 'API aktif.');
    }
}
