<?php

namespace App\Http\Controllers\Api;

use App\Enums\EscortReportReason;
use App\Enums\InvitationStatus;
use App\Enums\LandlordReportReason;
use App\Http\Controllers\Controller;
use App\Http\Resources\FlatResource;
use App\Models\Escort;
use App\Models\Flat;
use App\Models\FlatPhoto;
use App\Models\FlatReport;
use App\Models\Landlord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FlatGalleryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 12);

        if ($actor instanceof Landlord) {
            $flats = Flat::query()
                ->where('landlord_id', $actor->id)
                ->with(['photos'])
                ->withCount('votes')
                ->orderByDesc('id')
                ->paginate($perPage)
                ->withQueryString();

            $flats->getCollection()->transform(function (Flat $flat): Flat {
                return $flat->setAttribute('my_vote', null);
            });

            return FlatResource::collection($flats)->response();
        }

        if (! $actor instanceof Escort) {
            return response()->json([
                'message' => 'Only landlords and escorts can access flats.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $flats = $this->escortAccessibleFlatsQuery($actor)
            ->with(['photos'])
            ->withCount('votes')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $flats->getCollection()->transform(function (Flat $flat) use ($actor): Flat {
            $flat->setAttribute('my_vote', $flat->votes()
                ->where('escort_id', $actor->id)
                ->value('is_favorite'));

            return $flat;
        });

        return FlatResource::collection($flats)->response();
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

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $flat = $landlord->flats()->create($validated);

        return FlatResource::make(
            $flat->load(['photos'])->loadCount('votes')->setAttribute('my_vote', null)
        )->response()->setStatusCode(JsonResponse::HTTP_CREATED);
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

        return \App\Http\Resources\FlatPhotoResource::make($photo)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
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
        $actor = $request->user();

        if (! $this->canAccessPhoto($actor, $photo)) {
            return response()->json([
                'message' => 'You do not have access to this photo.',
            ], JsonResponse::HTTP_FORBIDDEN);
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

        $vote = $flat->votes()->updateOrCreate(
            ['escort_id' => $escort->id],
            ['is_favorite' => $validated['is_favorite'] ?? true],
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

        $report = FlatReport::query()->updateOrCreate([
            'flat_id' => $flat->id,
            'reporter_escort_id' => $escort->id,
            'reported_landlord_id' => $flat->landlord_id,
        ], [
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
        ], $report->wasRecentlyCreated ? JsonResponse::HTTP_CREATED : JsonResponse::HTTP_OK);
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
            'escort_id' => ['required', 'integer', 'exists:escorts,id'],
            'reason_code' => ['required', 'string', 'in:'.implode(',', array_column(LandlordReportReason::cases(), 'value'))],
        ]);

        $escort = Escort::query()->findOrFail($validated['escort_id']);

        if (! $this->landlordCanReportEscort($landlord, $escort)) {
            return response()->json([
                'message' => 'You do not have access to report this escort.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $report = FlatReport::query()->updateOrCreate([
            'flat_id' => $flat->id,
            'reporter_landlord_id' => $landlord->id,
            'reported_escort_id' => $escort->id,
        ], [
            'reason_code' => $validated['reason_code'],
        ]);

        return response()->json([
            'data' => [
                'id' => $report->id,
                'flat_id' => $report->flat_id,
                'reporter_landlord_id' => $report->reporter_landlord_id,
                'reported_escort_id' => $report->reported_escort_id,
                'reason_code' => $report->reason_code,
            ],
        ], $report->wasRecentlyCreated ? JsonResponse::HTTP_CREATED : JsonResponse::HTTP_OK);
    }

    private function escortAccessibleFlatsQuery(Escort $escort): Builder
    {
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
            return $actor->is_verified && $photo->flat->landlord_id === $actor->id;
        }

        if ($actor instanceof Escort) {
            return $this->escortCanAccessFlat($actor, $photo->flat);
        }

        return false;
    }

    private function landlordCanReportEscort(Landlord $landlord, Escort $escort): bool
    {
        return $landlord->invitations()
            ->where('phone_number', $escort->phone_number)
            ->where('status', InvitationStatus::Accepted)
            ->exists();
    }
}
