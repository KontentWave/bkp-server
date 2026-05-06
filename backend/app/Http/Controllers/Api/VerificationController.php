<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\Escort;
use App\Models\Invitation;
use App\Models\Landlord;
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

        $invitation->update([
            'status' => InvitationStatus::Accepted,
        ]);

        if ($invitation->invited_role === 'landlord') {
            $landlord = Landlord::query()->firstOrNew([
                'phone_number' => $validated['phone_number'],
            ]);

            $landlord->public_key = $validated['public_key'];
            $landlord->is_verified = true;
            $landlord->save();

            $token = $landlord->createToken('landlord-device')->plainTextToken;

            return response()->json([
                'data' => [
                    'actor_type' => 'landlord',
                    'actor_id' => $landlord->id,
                    'landlord_id' => $landlord->id,
                    'phone_number' => $validated['phone_number'],
                    'invitation_status' => $invitation->status->value,
                    'token' => $token,
                ],
            ]);
        }

        $escort = Escort::query()->firstOrNew([
            'phone_number' => $validated['phone_number'],
        ]);

        $escort->public_key = $validated['public_key'];
        $escort->external_id = $invitation->escort_external_id;
        $escort->save();

        $token = $escort->createToken('escort-device')->plainTextToken;

        return response()->json([
            'data' => [
                'actor_type' => 'escort',
                'actor_id' => $escort->id,
                'escort_id' => $escort->id,
                'phone_number' => $escort->phone_number,
                'invitation_status' => $invitation->status->value,
                'token' => $token,
            ],
        ]);
    }
}
