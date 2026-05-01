<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Landlord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandlordTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! app()->environment('local', 'testing')) {
            return response()->json([
                'message' => 'Landlord bootstrap token issuance is disabled outside local environments.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'public_key' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $landlord = Landlord::query()->updateOrCreate(
            ['public_key' => $validated['public_key']],
            ['is_verified' => true],
        );

        $token = $landlord->createToken($validated['device_name'] ?? 'landlord-device')->plainTextToken;

        return response()->json([
            'data' => [
                'actor_type' => 'landlord',
                'actor_id' => $landlord->id,
                'landlord_id' => $landlord->id,
                'is_verified' => $landlord->is_verified,
                'token' => $token,
            ],
        ], JsonResponse::HTTP_CREATED);
    }
}
