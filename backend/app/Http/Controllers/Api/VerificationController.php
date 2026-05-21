<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Jobs\RetryEscortAdScrapeJob;
use App\Models\Escort;
use App\Models\Invitation;
use App\Models\Landlord;
use App\Services\EscortAds\EscortAdScrapeException;
use App\Services\EscortAds\EscortAdScraper;
use App\Services\EscortAds\EscortAdSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function __construct(private readonly EscortAdScraper $escortAdScraper) {}

    private function shouldEnforceEscortPhoneMatch(): bool
    {
        return (bool) config('services.amaterky.enforce_phone_match', true);
    }

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

        if ($invitation->invited_role === 'landlord') {
            $invitation->update([
                'status' => InvitationStatus::Accepted,
            ]);

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

        try {
            $snapshot = $this->revalidateEscortInvitation($invitation);
        } catch (EscortAdScrapeException $exception) {
            $this->queueRetryForTransientEscortScrapeFailure($exception, $invitation);

            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode);
        }

        if (
            $this->shouldEnforceEscortPhoneMatch()
            && $snapshot !== null
            && $snapshot->phoneNumber !== $validated['phone_number']
        ) {
            return response()->json([
                'message' => 'The escort ad phone changed before activation. Request a new invitation.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($snapshot !== null) {
            $resolvedPhoneNumber =
                ! $this->shouldEnforceEscortPhoneMatch()
                    ? $validated['phone_number']
                    : $snapshot->phoneNumber;
            $resolvedExternalId = $snapshot->externalId;
            $resolvedAdUrl = $snapshot->adUrl;
            $resolvedScrapedAt = $snapshot->scrapedAt;
        } else {
            $resolvedPhoneNumber = $validated['phone_number'];
            $resolvedExternalId = $invitation->escort_external_id;
            $resolvedAdUrl = $invitation->escort_ad_url;
            $resolvedScrapedAt = $invitation->phone_scraped_at;
        }

        $invitation->update([
            'status' => InvitationStatus::Accepted,
            'phone_number' => $resolvedPhoneNumber,
            'escort_external_id' => $resolvedExternalId,
            'escort_ad_url' => $resolvedAdUrl,
            'phone_scraped_at' => $resolvedScrapedAt,
        ]);

        $escort = Escort::query()->firstOrNew([
            'phone_number' => $resolvedPhoneNumber,
        ]);

        $escort->public_key = $validated['public_key'];
        $escort->external_id = $resolvedExternalId;
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

    private function revalidateEscortInvitation(Invitation $invitation): ?EscortAdSnapshot
    {
        if (! is_string($invitation->escort_ad_url) && ! is_int($invitation->escort_external_id)) {
            return null;
        }

        if (is_string($invitation->escort_ad_url) && $invitation->escort_ad_url !== '') {
            return $this->escortAdScraper->scrapeByUrl($invitation->escort_ad_url);
        }

        if (is_int($invitation->escort_external_id)) {
            return $this->escortAdScraper->scrapeByExternalId($invitation->escort_external_id);
        }

        return null;
    }

    private function queueRetryForTransientEscortScrapeFailure(
        EscortAdScrapeException $exception,
        Invitation $invitation,
    ): void {
        if (! $exception->transient) {
            return;
        }

        RetryEscortAdScrapeJob::dispatch(
            $invitation->escort_external_id,
            $invitation->escort_ad_url,
            'verification',
        );
    }
}
