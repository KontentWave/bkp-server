<?php

namespace App\Http\Controllers\Api;

use App\Enums\EscortReportReason;
use App\Enums\InvitationStatus;
use App\Enums\LandlordReportReason;
use App\Http\Controllers\Controller;
use App\Http\Resources\FlatPhotoResource;
use App\Http\Resources\FlatResource;
use App\Models\Escort;
use App\Models\Flat;
use App\Models\FlatPhoto;
use App\Models\FlatReport;
use App\Models\Landlord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FlatGalleryController extends Controller
{
    public function municipalities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);
        $query = trim((string) ($validated['query'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);

        if ($query === '') {
            return response()->json(['data' => []]);
        }

        $rows = \App\Models\Municipality::query()
            ->where('name', 'like', $query.'%')
            ->orderByRaw('case when name = ? then 0 else 1 end', [$query])
            ->orderBy('name')
            ->orderBy('district')
            ->limit($perPage)
            ->get(['id', 'name', 'district', 'region']);

        return response()->json([
            'data' => $rows->map(fn (\App\Models\Municipality $municipality): array => [
                'id' => $municipality->id,
                'name' => $municipality->name,
                'district' => $municipality->district,
                'region' => $municipality->region,
            ])->all(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 12);

        if ($actor instanceof Landlord) {
            $reportedEscortSummaryAccess = $this->buildReportedEscortSummaryAccess($actor);
            $flats = Flat::query()
                ->with(['photos', 'reports', 'municipality'])
                ->withCount([
                    'votes as votes_count' => fn (Builder $query) => $query->where('is_favorite', true),
                ])
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString();

            /** @var Collection<int, Flat> $flatCollection */
            $flatCollection = $flats->getCollection();

                $flatCollection->transform(function (Flat $flat) use ($actor): Flat {
                $landlordReportSummary = $this->buildFlatLandlordReportSummary($flat);

                return $flat
                    ->setAttribute('is_owned_by_viewer', $flat->landlord_id === $actor->id)
                    ->setAttribute('my_vote', null)
                    ->setAttribute('landlord_reports_count', $landlordReportSummary['count'])
                    ->setAttribute('landlord_report_reasons', $landlordReportSummary['reason_codes']);
            });

            return FlatResource::collection($flats)
                ->additional([
                    'reported_escorts_summary' => $reportedEscortSummaryAccess['data'],
                    'reported_escorts_summary_access' => [
                        'can_view_reports' => $reportedEscortSummaryAccess['can_view_reports'],
                        'message' => $reportedEscortSummaryAccess['message'],
                    ],
                ])
                ->response();
        }

        if (! $actor instanceof Escort) {
            return response()->json([
                'message' => 'Only landlords and escorts can access flats.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $flats = $this->escortAccessibleFlatsQuery($actor)
            ->with(['photos', 'reports', 'municipality'])
            ->withCount([
                'votes as votes_count' => fn (Builder $query) => $query->where('is_favorite', true),
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        /** @var Collection<int, Flat> $flatCollection */
        $flatCollection = $flats->getCollection();

        $flatCollection->transform(function (Flat $flat) use ($actor): Flat {
            $landlordReportSummary = $this->buildFlatLandlordReportSummary($flat);

            $flat->setAttribute('my_vote', $flat->votes()
                ->where('escort_id', $actor->id)
                ->value('is_favorite'));
            $flat->setAttribute(
                'my_landlord_report_reasons',
                $flat->reports
                    ->where('reporter_escort_id', $actor->id)
                    ->where('reported_landlord_id', $flat->landlord_id)
                    ->sortByDesc('id')
                    ->pluck('reason_code')
                    ->values()
                    ->all(),
            );
            $flat->setAttribute('landlord_reports_count', $landlordReportSummary['count']);
            $flat->setAttribute('landlord_report_reasons', $landlordReportSummary['reason_codes']);

            return $flat;
        });

        return FlatResource::collection($flats)
            ->additional([
                'reported_escorts_summary' => [],
                'reported_escorts_summary_access' => [
                    'can_view_reports' => false,
                    'message' => 'Only landlords can review reported escorts.',
                ],
            ])
            ->response();
    }

    public function reportedEscortSummary(Request $request): JsonResponse
    {
        $landlord = $request->user();

        if (! $landlord instanceof Landlord) {
            return response()->json([
                'message' => 'Only landlords can access reported escort summaries.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $summaryAccess = $this->buildReportedEscortSummaryAccess($landlord);

        return response()->json([
            'data' => $summaryAccess['data'],
            'meta' => [
                'can_view_reports' => $summaryAccess['can_view_reports'],
                'message' => $summaryAccess['message'],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $landlord = $request->user();

        if (! $landlord instanceof Landlord) {
            return response()->json([
                'message' => 'Only landlords can create flats.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (! $landlord->is_verified) {
            return response()->json([
                'message' => 'Landlord verification is required.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $validated = $this->validateFlatPayload($request);

        $flat = $landlord->flats()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'municipality_id' => data_get($validated, 'municipality_id'),
            'contact_phone' => data_get($validated, 'contact.phone'),
            'contact_email' => data_get($validated, 'contact.email'),
            'whatsapp_url' => data_get($validated, 'contact.whatsapp_url'),
            'telegram_url' => data_get($validated, 'contact.telegram_url'),
            'viber_url' => data_get($validated, 'contact.viber_url'),
        ]);

        return FlatResource::make(
            $flat->load(['photos', 'municipality'])->loadCount([
                'votes as votes_count' => fn (Builder $query) => $query->where('is_favorite', true),
            ])->setAttribute('my_vote', null)
                ->setAttribute('is_owned_by_viewer', true)
                ->setAttribute('landlord_reports_count', 0)
                ->setAttribute('landlord_report_reasons', [])
        )->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, Flat $flat): JsonResponse
    {
        $landlord = $request->user();

        if (! $landlord instanceof Landlord) {
            return response()->json([
                'message' => 'Only landlords can update flats.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (! $landlord->is_verified || $flat->landlord_id !== $landlord->id) {
            return response()->json([
                'message' => 'You do not own this flat.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $validated = $this->validateFlatPayload($request);

        $flat->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'municipality_id' => data_get($validated, 'municipality_id'),
            'contact_phone' => data_get($validated, 'contact.phone'),
            'contact_email' => data_get($validated, 'contact.email'),
            'whatsapp_url' => data_get($validated, 'contact.whatsapp_url'),
            'telegram_url' => data_get($validated, 'contact.telegram_url'),
            'viber_url' => data_get($validated, 'contact.viber_url'),
        ]);

        return FlatResource::make(
            $flat->fresh(['photos', 'municipality'])?->loadCount([
                'votes as votes_count' => fn (Builder $query) => $query->where('is_favorite', true),
            ])?->setAttribute('my_vote', null)
                ->setAttribute('is_owned_by_viewer', true)
                ->setAttribute('landlord_reports_count', $this->buildFlatLandlordReportSummary($flat)['count'])
                ->setAttribute('landlord_report_reasons', $this->buildFlatLandlordReportSummary($flat)['reason_codes'])
        )->response();
    }

    public function destroy(Request $request, Flat $flat): JsonResponse
    {
        $landlord = $request->user();

        if (! $landlord instanceof Landlord) {
            return response()->json([
                'message' => 'Only landlords can delete flats.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (! $landlord->is_verified || $flat->landlord_id !== $landlord->id) {
            return response()->json([
                'message' => 'You do not own this flat.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        foreach ($flat->photos as $photo) {
            Storage::disk($photo->storage_disk)->delete($photo->storage_path);
        }

        $flat->delete();

        return response()->json([], JsonResponse::HTTP_NO_CONTENT);
    }

    public function storePhoto(Request $request, Flat $flat): JsonResponse
    {
        $landlord = $request->user();

        if (! $landlord instanceof Landlord) {
            return response()->json([
                'message' => 'Only landlords can upload flat photos.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (! $landlord->is_verified || $flat->landlord_id !== $landlord->id) {
            return response()->json([
                'message' => 'You do not own this flat.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'photo' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $photoFile = $validated['photo'];
        $storageDisk = 'public';
        $storagePath = $photoFile->store('flat-photos', $storageDisk);
        $sortOrder = (int) $flat->photos()->max('sort_order') + 1;

        $photo = $flat->photos()->create([
            'storage_disk' => $storageDisk,
            'storage_path' => $storagePath,
            'original_filename' => $photoFile->getClientOriginalName(),
            'mime_type' => $photoFile->getMimeType() ?? 'application/octet-stream',
            'byte_size' => $photoFile->getSize() ?? 0,
            'sort_order' => $sortOrder,
        ]);

        return FlatPhotoResource::make($photo)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFlatPayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:20'],
            'description' => ['required', 'string'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'contact.phone' => ['required', 'string', 'max:32'],
            'contact.email' => ['required', 'email:rfc', 'max:255'],
            'contact.whatsapp_url' => ['nullable', 'string', 'max:2048'],
            'contact.telegram_url' => ['nullable', 'string', 'max:2048'],
            'contact.viber_url' => ['nullable', 'string', 'max:2048'],
        ]);
    }

    public function destroyPhoto(Request $request, FlatPhoto $photo): JsonResponse
    {
        $landlord = $request->user();

        if (! $landlord instanceof Landlord) {
            return response()->json([
                'message' => 'Only landlords can delete flat photos.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (! $landlord->is_verified || $photo->flat->landlord_id !== $landlord->id) {
            return response()->json([
                'message' => 'You do not own this photo.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        Storage::disk($photo->storage_disk)->delete($photo->storage_path);
        $photo->delete();

        return response()->json([], JsonResponse::HTTP_NO_CONTENT);
    }

    public function showPhotoContent(Request $request, FlatPhoto $photo): BinaryFileResponse|JsonResponse
    {
        if (! Storage::disk($photo->storage_disk)->exists($photo->storage_path)) {
            return response()->json([
                'message' => 'Photo file not found.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $response = response()->file(Storage::disk($photo->storage_disk)->path($photo->storage_path), [
            'Cache-Control' => 'no-store, private',
            'Content-Type' => $photo->mime_type,
            'Content-Disposition' => 'inline; filename="'.$photo->original_filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function vote(Request $request, Flat $flat): JsonResponse
    {
        $escort = $request->user();

        if (! $escort instanceof Escort) {
            return response()->json([
                'message' => 'Only escorts can vote on flats.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (! $this->escortCanAccessFlat($escort, $flat)) {
            return response()->json([
                'message' => 'You do not have access to this flat.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'is_favorite' => ['sometimes', 'boolean'],
        ]);

        $isFavorite = $validated['is_favorite'] ?? true;
        $existingVote = $flat->votes()
            ->where('escort_id', $escort->id)
            ->first();

        if ($isFavorite && (! $existingVote?->is_favorite)) {
            $favoriteVotesCount = $flat->votes()
                ->where('is_favorite', true)
                ->count();

            if ($favoriteVotesCount >= 10) {
                return response()->json([
                    'message' => 'This flat already reached the maximum of 10 likes.',
                ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $vote = $flat->votes()->updateOrCreate(
            ['escort_id' => $escort->id],
            ['is_favorite' => $isFavorite],
        );

        return response()->json([
            'data' => [
                'flat_id' => $flat->id,
                'escort_id' => $escort->id,
                'is_favorite' => $vote->is_favorite,
            ],
        ]);
    }

    public function reportLandlord(Request $request, Flat $flat): JsonResponse
    {
        $escort = $request->user();

        if (! $escort instanceof Escort) {
            return response()->json([
                'message' => 'Only escorts can report landlords.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (! $this->escortCanAccessFlat($escort, $flat)) {
            return response()->json([
                'message' => 'You do not have access to this flat.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'reason_code' => ['required', 'string', 'in:'.implode(',', array_column(EscortReportReason::cases(), 'value'))],
        ]);

        $existingReport = FlatReport::query()->where([
            'flat_id' => $flat->id,
            'reporter_escort_id' => $escort->id,
            'reported_landlord_id' => $flat->landlord_id,
            'reason_code' => $validated['reason_code'],
        ])->first();

        if ($existingReport) {
            return response()->json([
                'message' => sprintf(
                    'You already reported this landlord for %s.',
                    str_replace('_', ' ', $validated['reason_code'])
                ),
            ], JsonResponse::HTTP_CONFLICT);
        }

        $report = FlatReport::query()->create([
            'flat_id' => $flat->id,
            'reporter_escort_id' => $escort->id,
            'reported_landlord_id' => $flat->landlord_id,
            'reason_code' => $validated['reason_code'],
        ]);

        return response()->json([
            'data' => [
                'id' => $report->id,
                'flat_id' => $report->flat_id,
                'reporter_escort_id' => $report->reporter_escort_id,
                'reported_landlord_id' => $report->reported_landlord_id,
                'reason_code' => $report->reason_code,
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    public function reportEscort(Request $request, Flat $flat): JsonResponse
    {
        $landlord = $request->user();

        if (! $landlord instanceof Landlord) {
            return response()->json([
                'message' => 'Only landlords can report escorts.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        if (! $landlord->is_verified || $flat->landlord_id !== $landlord->id) {
            return response()->json([
                'message' => 'You do not own this flat.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'escort_external_id' => ['required', 'integer', 'min:1'],
            'reason_code' => ['required', 'string', 'in:'.implode(',', array_column(LandlordReportReason::cases(), 'value'))],
        ]);

        $escort = Escort::query()
            ->where('external_id', $validated['escort_external_id'])
            ->first();

        $existingReport = FlatReport::query()->where([
            'flat_id' => $flat->id,
            'reporter_landlord_id' => $landlord->id,
            'reported_escort_external_id' => $validated['escort_external_id'],
            'reason_code' => $validated['reason_code'],
        ])->first();

        if ($existingReport) {
            return response()->json([
                'message' => sprintf(
                    'You already reported this escort for %s.',
                    str_replace('_', ' ', $validated['reason_code'])
                ),
            ], JsonResponse::HTTP_CONFLICT);
        }

        $report = FlatReport::query()->create([
            'flat_id' => $flat->id,
            'reporter_landlord_id' => $landlord->id,
            'reported_escort_id' => $escort?->id,
            'reported_escort_external_id' => $validated['escort_external_id'],
            'reason_code' => $validated['reason_code'],
        ]);

        return response()->json([
            'data' => [
                'id' => $report->id,
                'flat_id' => $report->flat_id,
                'reporter_landlord_id' => $report->reporter_landlord_id,
                'reported_escort_id' => $report->reported_escort_id,
                'reported_escort_external_id' => $report->reported_escort_external_id,
                'reason_code' => $report->reason_code,
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    private function escortAccessibleFlatsQuery(Escort $escort): Builder
    {
        if (! (bool) config('services.flat_gallery.enforce_escort_invitation_access', true)) {
            return Flat::query();
        }

        return Flat::query()->whereHas('landlord.invitations', function (Builder $query) use ($escort): void {
            $query
                ->where('phone_number', $escort->phone_number)
                ->where('status', InvitationStatus::Accepted);
        });
    }

    private function escortCanAccessFlat(Escort $escort, Flat $flat): bool
    {
        return $this->escortAccessibleFlatsQuery($escort)
            ->whereKey($flat->id)
            ->exists();
    }

    private function canAccessPhoto(mixed $actor, FlatPhoto $photo): bool
    {
        if ($actor instanceof Landlord) {
            return $actor->is_verified;
        }

        if ($actor instanceof Escort) {
            return $this->escortCanAccessFlat($actor, $photo->flat);
        }

        return false;
    }

    /**
     * @return array<int, array<string, int|string|null>>
     */
    private function buildLandlordReportedEscortSummary(Landlord $landlord): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, FlatReport> $reports */
        $reports = FlatReport::query()
            ->with([
                'flat:id,title,contact_phone',
                'reportedEscort:id,phone_number',
            ])
            ->whereNotNull('reported_escort_external_id')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return $reports
            ->map(fn (FlatReport $report): array => [
                'report_id' => $report->id,
                'flat_id' => $report->flat_id,
                'flat_title' => $report->flat?->title,
                'flat_contact_phone' => $report->flat?->contact_phone,
                'escort_id' => $report->reported_escort_id,
                'escort_external_id' => $report->reported_escort_external_id,
                'phone_number' => $report->reportedEscort?->phone_number,
                'reason_code' => $report->reason_code,
                'updated_at' => $report->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array{can_view_reports: bool, message: string, data: array<int, array<string, int|string|null>>}
     */
    private function buildReportedEscortSummaryAccess(Landlord $landlord): array
    {
        $hasPublishedFlat = Flat::query()
            ->where('landlord_id', $landlord->id)
            ->exists();

        if (! $hasPublishedFlat) {
            return [
                'can_view_reports' => false,
                'message' => 'Publish at least one flat before reviewing reported escorts.',
                'data' => [],
            ];
        }

        return [
            'can_view_reports' => true,
            'message' => '',
            'data' => $this->buildLandlordReportedEscortSummary($landlord),
        ];
    }

    /**
     * @return array{count: int, reason_codes: array<int, string>}
     */
    private function buildFlatLandlordReportSummary(Flat $flat): array
    {
        $reasonCodes = $flat->reports
            ->where('reported_landlord_id', $flat->landlord_id)
            ->sortByDesc('updated_at')
            ->pluck('reason_code')
            ->filter()
            ->values()
            ->all();

        return [
            'count' => count($reasonCodes),
            'reason_codes' => $reasonCodes,
        ];
    }
}
