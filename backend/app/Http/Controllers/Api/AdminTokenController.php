<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! app()->environment('local', 'testing')) {
            return response()->json([
                'message' => 'Admin bootstrap token issuance is disabled outside local environments.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $admin = Admin::query()->firstOrCreate([
            'name' => $validated['name'],
        ]);

        $token = $admin->createToken($validated['device_name'] ?? 'admin-device')->plainTextToken;

        return response()->json([
            'data' => [
                'actor_type' => 'admin',
                'actor_id' => $admin->id,
                'admin_id' => $admin->id,
                'name' => $admin->name,
                'token' => $token,
            ],
        ], JsonResponse::HTTP_CREATED);
    }
}
