<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Landlord;
use App\Notifications\InvitationSmsNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class InvitationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return $this->storeInvitation($request);
    }

    public function storeFromBrowser(Request $request): JsonResponse
    {
        return $this->storeInvitation($request);
    }

    private function storeInvitation(Request $request): JsonResponse
    {
        $landlord = $request->user();

        if (! $landlord instanceof Landlord) {
            return response()->json([
                'message' => 'Only landlords can create invitations.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (! $landlord->is_verified) {
            return response()->json([
                'message' => 'Landlord verification is required.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:32'],
        ]);

        $otpCode = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $invitation = Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => $validated['phone_number'],
            'otp_token' => hash('sha256', $otpCode),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addMinutes(10),
        ]);

        Notification::route('vonage', $invitation->phone_number)
            ->notify(new InvitationSmsNotification($otpCode));

        return response()->json([
            'data' => [
                'id' => $invitation->id,
                'status' => $invitation->status->value,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ],
        ], JsonResponse::HTTP_CREATED);
    }
}
