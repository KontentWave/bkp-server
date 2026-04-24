<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\Escort;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:32'],
            'otp' => ['required', 'digits:4'],
            'public_key' => ['required', 'string'],
        ]);

        $invitation = Invitation::query()
            ->where('phone_number', $validated['phone_number'])
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($invitation === null || ! hash_equals($invitation->otp_token, hash('sha256', $validated['otp']))) {
            return response()->json([
                'message' => 'The provided OTP is invalid.',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $escort = Escort::query()->updateOrCreate(
            ['phone_number' => $validated['phone_number']],
            ['public_key' => $validated['public_key']],
        );

        $invitation->update([
            'status' => InvitationStatus::Accepted,
        ]);

        $token = $escort->createToken('escort-device')->plainTextToken;

        return response()->json([
            'data' => [
                'escort_id' => $escort->id,
                'phone_number' => $escort->phone_number,
                'invitation_status' => $invitation->status->value,
                'token' => $token,
            ],
        ]);
    }
}
