<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Jobs\RetryEscortAdScrapeJob;
use App\Jobs\SendSmsMessageJob;
use App\Models\Escort;
use App\Models\Invitation;
use App\Models\Landlord;
use App\Services\EscortAds\EscortAdScrapeException;
use App\Services\EscortAds\EscortAdScraper;
use App\Services\EscortAds\EscortAdSnapshot;
use App\Services\PhoneNumberNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function __construct(
        private readonly EscortAdScraper $escortAdScraper,
        private readonly PhoneNumberNormalizer $phoneNumberNormalizer,
    ) {}

    private function shouldEnforceEscortPhoneMatch(): bool
    {
        return (bool) config('services.amaterky.enforce_phone_match', true);
    }

    private function resolveSmsDeliveryPhoneNumber(string $targetPhoneNumber): string
    {
        $overridePhoneNumber = trim((string) config('services.smstools.local_override_phone', ''));

        if ($overridePhoneNumber === '' || ! app()->environment('local', 'testing')) {
            return $targetPhoneNumber;
        }

        return $overridePhoneNumber;
    }

    public function store(Request $request): JsonResponse
    {
        return $this->storeInvitation($request);
    }

    public function storeFromBrowser(Request $request): JsonResponse
    {
        return $this->storeInvitation($request);
    }

    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:32'],
            'invited_role' => ['required', Rule::in(['escort', 'landlord'])],
        ]);

        $normalizedPhoneNumber = $this->phoneNumberNormalizer->normalize($validated['phone_number']);

        if ($normalizedPhoneNumber === null) {
            return response()->json([
                'message' => 'The provided phone number is not in a supported format.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        Invitation::query()
            ->where('phone_number', $normalizedPhoneNumber)
            ->where('invited_role', $validated['invited_role'])
            ->where('status', InvitationStatus::Pending)
            ->update([
                'status' => InvitationStatus::Declined,
            ]);

        return $this->createInvitation(
            actor: null,
            invitedRole: $validated['invited_role'],
            targetPhoneNumber: $normalizedPhoneNumber,
            escortExternalId: null,
            escortAdUrl: null,
            phoneScrapedAt: null,
        );
    }

    private function storeInvitation(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof Landlord && ! $actor instanceof Escort) {
            return response()->json([
                'message' => 'Only authenticated landlords or escorts can create invitations.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if ($actor instanceof Landlord && ! $actor->is_verified) {
            return response()->json([
                'message' => 'Landlord verification is required.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'phone_number' => [
                Rule::requiredIf($request->input('invited_role') === 'landlord'),
                'nullable',
                'string',
                'max:32',
            ],
            'invited_role' => ['required', Rule::in(['escort', 'landlord'])],
            'escort_external_id' => [
                Rule::requiredIf(
                    $request->input('invited_role') === 'escort'
                    && ! $request->filled('escort_ad_url')
                ),
                Rule::prohibitedIf($request->input('invited_role') === 'landlord'),
                'nullable',
                'integer',
                'min:1',
            ],
            'escort_ad_url' => [
                Rule::requiredIf(
                    $request->input('invited_role') === 'escort'
                    && ! $request->filled('escort_external_id')
                ),
                Rule::prohibitedIf($request->input('invited_role') === 'landlord'),
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $normalizedPhoneNumber = $this->phoneNumberNormalizer->normalize($validated['phone_number'] ?? null);

        if (($validated['phone_number'] ?? null) !== null && $normalizedPhoneNumber === null) {
            return response()->json([
                'message' => 'The provided phone number is not in a supported format.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validated['phone_number'] = $normalizedPhoneNumber;

        $targetPhoneNumber = $validated['phone_number'] ?? null;
        $escortExternalId = $validated['escort_external_id'] ?? null;
        $escortAdUrl = $validated['escort_ad_url'] ?? null;
        $phoneScrapedAt = null;

        if ($validated['invited_role'] === 'escort') {
            try {
                $snapshot = $this->scrapeEscortInvitation($escortExternalId, $escortAdUrl);
            } catch (EscortAdScrapeException $exception) {
                $this->queueRetryForTransientEscortScrapeFailure(
                    $exception,
                    $escortExternalId,
                    $escortAdUrl,
                    'invitation',
                );

                return response()->json([
                    'message' => $exception->getMessage(),
                ], $exception->statusCode);
            }

            if (
                $this->shouldEnforceEscortPhoneMatch()
                && ($validated['phone_number'] ?? null) !== null
                && $validated['phone_number'] !== $snapshot->phoneNumber
            ) {
                return response()->json([
                    'message' => 'The provided phone number does not match the current phone visible on the escort ad.',
                ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $targetPhoneNumber =
                ! $this->shouldEnforceEscortPhoneMatch()
                && ($validated['phone_number'] ?? null) !== null
                    ? $validated['phone_number']
                    : $snapshot->phoneNumber;
            $escortExternalId = $snapshot->externalId;
            $escortAdUrl = $snapshot->adUrl;
            $phoneScrapedAt = $snapshot->scrapedAt;
        }

        return $this->createInvitation(
            actor: $actor,
            invitedRole: $validated['invited_role'],
            targetPhoneNumber: $targetPhoneNumber,
            escortExternalId: $escortExternalId,
            escortAdUrl: $escortAdUrl,
            phoneScrapedAt: $phoneScrapedAt,
        );
    }

    private function createInvitation(
        Landlord|Escort|null $actor,
        string $invitedRole,
        string $targetPhoneNumber,
        ?int $escortExternalId,
        ?string $escortAdUrl,
        $phoneScrapedAt,
    ): JsonResponse {
        $otpCode = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $otpTtlMinutes = (int) config('services.invitation.otp_ttl_minutes', 60);

        $invitationAttributes = [
            'landlord_id' => $actor instanceof Landlord ? $actor->id : null,
            'phone_number' => $targetPhoneNumber,
            'phone_scraped_at' => $phoneScrapedAt,
            'invited_role' => $invitedRole,
            'escort_external_id' => $escortExternalId,
            'escort_ad_url' => $escortAdUrl,
            'otp_token' => hash('sha256', $otpCode),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addMinutes($otpTtlMinutes),
        ];

        if ($actor !== null) {
            $invitationAttributes['inviter_type'] = $actor::class;
            $invitationAttributes['inviter_id'] = $actor->id;
        }

        $invitation = Invitation::query()->create($invitationAttributes);

        $deliveryPhoneNumber = $this->resolveSmsDeliveryPhoneNumber($invitation->phone_number);

        SendSmsMessageJob::dispatch(
            $deliveryPhoneNumber,
            "You have been invited. Use code {$otpCode} to continue.",
        );

        return response()->json([
            'data' => [
                'id' => $invitation->id,
                'invited_role' => $invitation->invited_role,
                'status' => $invitation->status->value,
                'phone_number' => $invitation->phone_number,
                'delivery_phone_number' => $deliveryPhoneNumber,
                'delivery_overridden' => $deliveryPhoneNumber !== $invitation->phone_number,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    private function scrapeEscortInvitation(?int $escortExternalId, ?string $escortAdUrl): EscortAdSnapshot
    {
        if ($escortAdUrl !== null && $escortAdUrl !== '') {
            return $this->escortAdScraper->scrapeByUrl($escortAdUrl);
        }

        if ($escortExternalId === null) {
            throw new EscortAdScrapeException('Provide an escort ad ID or escort ad URL.');
        }

        return $this->escortAdScraper->scrapeByExternalId($escortExternalId);
    }

    private function queueRetryForTransientEscortScrapeFailure(
        EscortAdScrapeException $exception,
        ?int $escortExternalId,
        ?string $escortAdUrl,
        string $reason,
    ): void {
        if (! $exception->transient) {
            return;
        }

        RetryEscortAdScrapeJob::dispatch($escortExternalId, $escortAdUrl, $reason);
    }
}
