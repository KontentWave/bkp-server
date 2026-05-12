<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof Admin) {
            return response()->json([
                'message' => 'Only admins can read backend logs.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $logPath = storage_path('logs/laravel.log');

        if (! is_file($logPath)) {
            return response()->json([
                'data' => [
                    'path' => $logPath,
                    'content' => '',
                    'size_bytes' => 0,
                    'updated_at' => null,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'path' => $logPath,
                'content' => file_get_contents($logPath) ?: '',
                'size_bytes' => filesize($logPath) ?: 0,
                'updated_at' => now()->setTimestamp((int) filemtime($logPath))->toIso8601String(),
            ],
        ]);
    }
}
