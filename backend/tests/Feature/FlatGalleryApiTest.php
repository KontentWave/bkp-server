<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Enums\EscortReportReason;
use App\Enums\LandlordReportReason;
use App\Models\Escort;
use App\Models\Flat;
use App\Models\FlatReport;
use App\Models\Invitation;
use App\Models\Landlord;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlatGalleryApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function flat_creation_requires_landlord_role(): void
    {
        [$privateKey, $escort] = $this->createEscortSession('+421900111000');

        $payload = [
            'title' => 'Old Town Loft',
            'description' => 'Top floor with balcony.',
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats', $timestamp, $nonce);

        $response = $this->withToken($escort->createToken('escort-device-secondary')->plainTextToken)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->postJson('/api/flats', $payload);

        $response->assertForbidden()->assertJsonPath('message', 'Only landlords can create flats.');
        $this->assertDatabaseCount('flats', 0);
    }

    #[Test]
    public function landlord_can_update_owned_flat_details(): void
    {
        [$privateKey, $token, $landlord] = $this->createLandlordSession();
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Flat 2',
            'description' => '1 Room flat',
            'contact_phone' => '+421917047260',
            'contact_email' => 'admin@zafo-forum.sk',
        ]);

        $payload = [
            'title' => 'Flat 2 updated',
            'description' => '2 Room flat with balcony',
            'contact' => [
                'phone' => '+421900123456',
                'email' => 'owner-updated@example.test',
                'whatsapp_url' => 'https://wa.me/421900123456',
                'telegram_url' => 'https://t.me/updated_owner',
                'viber_url' => 'viber://chat?number=%2B421900123456',
            ],
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'PATCH', '/api/flats/'.$flat->id, $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->patchJson('/api/flats/'.$flat->id, $payload);

        $response->assertOk()
            ->assertJsonPath('data.id', $flat->id)
            ->assertJsonPath('data.title', 'Flat 2 updated')
            ->assertJsonPath('data.description', '2 Room flat with balcony')
            ->assertJsonPath('data.contact.phone', '+421900123456')
            ->assertJsonPath('data.contact.email', 'owner-updated@example.test')
            ->assertJsonPath('data.contact.whatsapp_url', 'https://wa.me/421900123456')
            ->assertJsonPath('data.contact.telegram_url', 'https://t.me/updated_owner')
            ->assertJsonPath('data.contact.viber_url', 'viber://chat?number=%2B421900123456')
            ->assertJsonPath('data.is_owned_by_viewer', true);

        $this->assertDatabaseHas('flats', [
            'id' => $flat->id,
            'title' => 'Flat 2 updated',
            'description' => '2 Room flat with balcony',
            'contact_phone' => '+421900123456',
            'contact_email' => 'owner-updated@example.test',
            'whatsapp_url' => 'https://wa.me/421900123456',
            'telegram_url' => 'https://t.me/updated_owner',
            'viber_url' => 'viber://chat?number=%2B421900123456',
        ]);
    }

    #[Test]
    public function landlord_can_delete_owned_flat_and_related_files(): void
    {
        Storage::fake('public');

        [$privateKey, $token, $landlord] = $this->createLandlordSession();
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Delete Flat',
            'description' => 'Delete me permanently.',
        ]);

        Storage::disk('public')->put('flat-photos/delete-flat.jpg', 'photo-bytes');
        $flat->photos()->create([
            'storage_disk' => 'public',
            'storage_path' => 'flat-photos/delete-flat.jpg',
            'original_filename' => 'delete-flat.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 11,
            'sort_order' => 1,
        ]);
        $escort = Escort::query()->create([
            'external_id' => 29999,
            'phone_number' => '+421900119999',
            'public_key' => $this->generateEcKeyPair()[1],
        ]);
        $flat->votes()->create([
            'escort_id' => $escort->id,
            'is_favorite' => true,
        ]);
        FlatReport::query()->create([
            'flat_id' => $flat->id,
            'reporter_landlord_id' => $landlord->id,
            'reported_escort_id' => $escort->id,
            'reported_escort_external_id' => $escort->external_id,
            'reason_code' => LandlordReportReason::Drugs->value,
        ]);

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'DELETE', '/api/flats/'.$flat->id, $timestamp, $nonce);

        $response = $this->call('DELETE', '/api/flats/'.$flat->id, [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertNoContent();
        $this->assertDatabaseMissing('flats', ['id' => $flat->id]);
        $this->assertDatabaseCount('votes', 0);
        $this->assertDatabaseCount('flat_reports', 0);
        $this->assertDatabaseCount('flat_photos', 0);
        $this->assertFalse(Storage::disk('public')->exists('flat-photos/delete-flat.jpg'));
    }

    #[Test]
    public function landlord_cannot_delete_another_landlords_flat(): void
    {
        [$privateKey, $token] = $this->createLandlordSession();
        $foreignLandlord = Landlord::query()->create(['is_verified' => true]);
        $flat = Flat::query()->create([
            'landlord_id' => $foreignLandlord->id,
            'title' => 'Foreign Delete Flat',
            'description' => 'Should stay put.',
        ]);

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'DELETE', '/api/flats/'.$flat->id, $timestamp, $nonce);

        $response = $this->call('DELETE', '/api/flats/'.$flat->id, [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertForbidden()->assertJsonPath('message', 'You do not own this flat.');
        $this->assertDatabaseHas('flats', ['id' => $flat->id]);
    }

    #[Test]
    public function landlord_can_search_municipalities(): void
    {
        [, $token] = $this->createLandlordSession();

        $expectedMunicipality = Municipality::query()
            ->where('name', 'Banská Bystrica')
            ->where('district', 'Banská Bystrica')
            ->where('region', 'Banskobystrický kraj')
            ->firstOrFail();

        $response = $this->withToken($token)
            ->getJson('/api/municipalities?query=Bansk');

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $expectedMunicipality->id,
            'name' => 'Banská Bystrica',
            'district' => 'Banská Bystrica',
            'region' => 'Banskobystrický kraj',
        ]);

        foreach ($response->json('data') as $row) {
            $this->assertStringStartsWith('Bansk', $row['name']);
        }
    }

    #[Test]
    public function flat_gallery_scope_requires_approved_access(): void
    {
        [$privateKey, $escort, $token] = $this->createEscortSession('+421900111222');
        $allowedLandlord = Landlord::query()->create(['is_verified' => true]);
        $blockedLandlord = Landlord::query()->create(['is_verified' => true]);

        Invitation::query()->create([
            'landlord_id' => $allowedLandlord->id,
            'phone_number' => $escort->phone_number,
            'otp_token' => hash('sha256', '1001'),
            'status' => InvitationStatus::Accepted,
            'expires_at' => now()->addMinutes(10),
        ]);

        Invitation::query()->create([
            'landlord_id' => $blockedLandlord->id,
            'phone_number' => $escort->phone_number,
            'otp_token' => hash('sha256', '2002'),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addMinutes(10),
        ]);

        $allowedFlat = Flat::query()->create([
            'landlord_id' => $allowedLandlord->id,
            'title' => 'Allowed Flat',
            'description' => 'Visible to this escort.',
        ]);

        Flat::query()->create([
            'landlord_id' => $blockedLandlord->id,
            'title' => 'Blocked Flat',
            'description' => 'Must stay hidden.',
        ]);

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'GET', '/api/flats', $timestamp, $nonce);

        $response = $this->call('GET', '/api/flats', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $allowedFlat->id);
        $response->assertJsonMissing(['title' => 'Blocked Flat']);
        $response->assertJsonPath('meta.per_page', 12);
    }

    #[Test]
    public function escort_can_view_and_like_all_flats_when_invitation_access_is_disabled(): void
    {
        config(['services.flat_gallery.enforce_escort_invitation_access' => false]);

        [$privateKey, $escort, $token] = $this->createEscortSession('+421900111222');
        $firstLandlord = Landlord::query()->create(['is_verified' => true]);
        $secondLandlord = Landlord::query()->create(['is_verified' => true]);

        $visibleFlat = Flat::query()->create([
            'landlord_id' => $firstLandlord->id,
            'title' => 'Visible Flat',
            'description' => 'Should be visible without invitation gating.',
        ]);

        Flat::query()->create([
            'landlord_id' => $secondLandlord->id,
            'title' => 'Another Visible Flat',
            'description' => 'Should also be visible without invitation gating.',
        ]);

        $indexTimestamp = (string) now()->timestamp;
        $indexNonce = (string) Str::uuid();
        $indexSignature = $this->signHardwareContent('', $privateKey, 'GET', '/api/flats', $indexTimestamp, $indexNonce);

        $indexResponse = $this->call('GET', '/api/flats', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $indexNonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $indexTimestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $indexSignature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $indexResponse->assertOk();
        $indexResponse->assertJsonCount(2, 'data');
        $indexResponse->assertJsonFragment(['title' => 'Visible Flat']);
        $indexResponse->assertJsonFragment(['title' => 'Another Visible Flat']);

        $payload = ['is_favorite' => true];
        $voteTimestamp = (string) now()->timestamp;
        $voteNonce = (string) Str::uuid();
        $voteSignature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats/'.$visibleFlat->id.'/vote', $voteTimestamp, $voteNonce);

        $voteResponse = $this->withToken($token)->withHeaders([
            'X-Hardware-Nonce' => $voteNonce,
            'X-Hardware-Timestamp' => $voteTimestamp,
            'X-Hardware-Signature' => $voteSignature,
        ])->postJson('/api/flats/'.$visibleFlat->id.'/vote', $payload);

        $voteResponse->assertOk()
            ->assertJsonPath('data.flat_id', $visibleFlat->id)
            ->assertJsonPath('data.escort_id', $escort->id)
            ->assertJsonPath('data.is_favorite', true);
    }

    #[Test]
    public function landlord_listing_returns_owned_and_other_flats_and_hides_storage_internals(): void
    {
        Storage::fake('public');

        [$privateKey, $token, $landlord] = $this->createLandlordSession();
        $otherLandlord = Landlord::query()->create(['is_verified' => true]);

        $ownedFlat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Owned Flat',
            'description' => 'Should be visible.',
        ]);

        $ownedFlat->photos()->create([
            'storage_disk' => 'public',
            'storage_path' => 'flat-photos/owned-flat.jpg',
            'original_filename' => 'owned-flat.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 1024,
            'sort_order' => 1,
        ]);

        $otherFlat = Flat::query()->create([
            'landlord_id' => $otherLandlord->id,
            'title' => 'Other Flat',
            'description' => 'Should also be visible.',
        ]);

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'GET', '/api/flats', $timestamp, $nonce);

        $response = $this->call('GET', '/api/flats', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $otherFlat->id);
        $response->assertJsonPath('data.1.id', $ownedFlat->id);
        $response->assertJsonPath('data.0.is_owned_by_viewer', false);
        $response->assertJsonPath('data.1.is_owned_by_viewer', true);
        $response->assertJsonPath('data.1.photos.0.original_filename', 'owned-flat.jpg');
        $response->assertJsonPath('data.1.photos.0.content_url', '/api/photos/'.$ownedFlat->photos()->firstOrFail()->id.'/content');
        $response->assertJsonFragment(['title' => 'Other Flat']);
        $response->assertJsonMissingPath('data.1.photos.0.storage_disk');
        $response->assertJsonMissingPath('data.1.photos.0.storage_path');
    }

    #[Test]
    public function flat_listing_is_paginated_for_landlords(): void
    {
        [$privateKey, $token, $landlord] = $this->createLandlordSession();

        Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Flat A',
            'description' => 'First',
        ]);

        Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Flat B',
            'description' => 'Second',
        ]);

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'GET', '/api/flats?per_page=1', $timestamp, $nonce);

        $response = $this->call('GET', '/api/flats', ['per_page' => 1], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('meta.per_page', 1);
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'landlord_id',
                    'municipality_id',
                    'title',
                    'description',
                    'city',
                    'district',
                    'region',
                    'photos',
                    'votes_count',
                    'my_vote',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'links',
                'path',
                'per_page',
                'to',
                'total',
            ],
        ]);
    }

    #[Test]
    public function paginated_flat_resource_shape_is_frozen_for_mobile_contract(): void
    {
        Storage::fake('public');

        [$privateKey, $token, $landlord] = $this->createLandlordSession();
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Contract Flat',
            'description' => 'Frozen JSON shape.',
            'contact_phone' => '+421900111222',
            'contact_email' => 'owner@example.test',
            'whatsapp_url' => 'https://wa.me/421900111222',
            'telegram_url' => 'https://t.me/owner_example',
            'viber_url' => 'viber://chat?number=%2B421900111222',
        ]);
        $escort = Escort::query()->create([
            'external_id' => 29637,
            'phone_number' => '+421900111555',
            'public_key' => $this->generateEcKeyPair()[1],
        ]);
        $photo = $flat->photos()->create([
            'storage_disk' => 'public',
            'storage_path' => 'flat-photos/contract.jpg',
            'original_filename' => 'contract.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 128,
            'sort_order' => 1,
        ]);
        FlatReport::query()->create([
            'flat_id' => $flat->id,
            'reporter_landlord_id' => $landlord->id,
            'reported_escort_id' => $escort->id,
            'reported_escort_external_id' => 29637,
            'reason_code' => LandlordReportReason::Drugs->value,
        ]);

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'GET', '/api/flats?per_page=1', $timestamp, $nonce);

        $response = $this->call('GET', '/api/flats', ['per_page' => 1], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(
            ['id', 'landlord_id', 'municipality_id', 'title', 'description', 'city', 'district', 'region', 'contact', 'photos', 'votes_count', 'my_vote', 'is_owned_by_viewer', 'landlord_reports_count', 'landlord_report_reasons', 'my_landlord_report_reasons', 'created_at', 'updated_at'],
            array_keys($data['data'][0]),
        );
        $this->assertSame(
            ['phone', 'email', 'whatsapp_url', 'telegram_url', 'viber_url'],
            array_keys($data['data'][0]['contact']),
        );
        $this->assertSame('+421900111222', $data['data'][0]['contact']['phone']);
        $this->assertSame(
            ['id', 'flat_id', 'content_url', 'original_filename', 'mime_type', 'byte_size', 'sort_order', 'created_at', 'updated_at'],
            array_keys($data['data'][0]['photos'][0]),
        );
        $this->assertSame('/api/photos/'.$photo->id.'/content', $data['data'][0]['photos'][0]['content_url']);
        $this->assertSame(0, $data['data'][0]['landlord_reports_count']);
        $this->assertSame([], $data['data'][0]['landlord_report_reasons']);
        $this->assertArrayHasKey('reported_escorts_summary', $data);
        $this->assertSame(true, $data['reported_escorts_summary_access']['can_view_reports']);
        $this->assertSame('', $data['reported_escorts_summary_access']['message']);
        $this->assertSame(
            ['report_id', 'flat_id', 'flat_title', 'flat_contact_phone', 'escort_id', 'escort_external_id', 'phone_number', 'reason_code', 'updated_at'],
            array_keys($data['reported_escorts_summary'][0]),
        );
        $this->assertSame($flat->id, $data['reported_escorts_summary'][0]['flat_id']);
        $this->assertSame('Contract Flat', $data['reported_escorts_summary'][0]['flat_title']);
        $this->assertSame('+421900111222', $data['reported_escorts_summary'][0]['flat_contact_phone']);
        $this->assertSame($escort->id, $data['reported_escorts_summary'][0]['escort_id']);
        $this->assertSame(29637, $data['reported_escorts_summary'][0]['escort_external_id']);
        $this->assertSame('+421900111555', $data['reported_escorts_summary'][0]['phone_number']);
        $this->assertSame(LandlordReportReason::Drugs->value, $data['reported_escorts_summary'][0]['reason_code']);
        $this->assertSame(1, $data['meta']['per_page']);
        $this->assertSame(1, $data['meta']['total']);
    }

    #[Test]
    public function landlord_can_load_reported_escort_summary_without_loading_gallery(): void
    {
        [$privateKey, $token, $landlord] = $this->createLandlordSession();
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Moderated Flat',
            'description' => 'Summary only endpoint.',
        ]);
        $escort = Escort::query()->create([
            'external_id' => 29638,
            'phone_number' => '+421900111556',
            'public_key' => $this->generateEcKeyPair()[1],
        ]);

        FlatReport::query()->create([
            'flat_id' => $flat->id,
            'reporter_landlord_id' => $landlord->id,
            'reported_escort_id' => $escort->id,
            'reported_escort_external_id' => 29638,
            'reason_code' => LandlordReportReason::Hygiene->value,
        ]);

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'GET', '/api/reported-escorts-summary', $timestamp, $nonce);

        $response = $this->call('GET', '/api/reported-escorts-summary', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(true, $data['meta']['can_view_reports']);
        $this->assertSame('', $data['meta']['message']);

        $this->assertSame(
            ['report_id', 'flat_id', 'flat_title', 'flat_contact_phone', 'escort_id', 'escort_external_id', 'phone_number', 'reason_code', 'updated_at'],
            array_keys($data['data'][0]),
        );
        $this->assertSame($flat->id, $data['data'][0]['flat_id']);
        $this->assertSame('Moderated Flat', $data['data'][0]['flat_title']);
        $this->assertSame(null, $data['data'][0]['flat_contact_phone']);
        $this->assertSame($escort->id, $data['data'][0]['escort_id']);
        $this->assertSame(29638, $data['data'][0]['escort_external_id']);
        $this->assertSame('+421900111556', $data['data'][0]['phone_number']);
        $this->assertSame(LandlordReportReason::Hygiene->value, $data['data'][0]['reason_code']);
    }

    #[Test]
    public function landlord_reported_escort_summary_is_global_across_landlords(): void
    {
        $reporterLandlord = Landlord::query()->create([
            'public_key' => $this->generateEcKeyPair()[1],
            'is_verified' => true,
        ]);
        [$viewerPrivateKey, $viewerToken, $viewerLandlord] = $this->createLandlordSession();

        $reportedFlat = Flat::query()->create([
            'landlord_id' => $reporterLandlord->id,
            'title' => 'Reported Flat',
            'description' => 'Created by the reporting landlord.',
        ]);

        Flat::query()->create([
            'landlord_id' => $viewerLandlord->id,
            'title' => 'Viewer Flat',
            'description' => 'Keeps reported-escort access enabled for the viewer.',
        ]);

        $escort = Escort::query()->create([
            'external_id' => 29639,
            'phone_number' => '+421900111557',
            'public_key' => $this->generateEcKeyPair()[1],
        ]);

        FlatReport::query()->create([
            'flat_id' => $reportedFlat->id,
            'reporter_landlord_id' => $reporterLandlord->id,
            'reported_escort_id' => $escort->id,
            'reported_escort_external_id' => 29639,
            'reason_code' => LandlordReportReason::Drugs->value,
        ]);

        $summaryTimestamp = (string) now()->timestamp;
        $summaryNonce = (string) Str::uuid();
        $summarySignature = $this->signHardwareContent('', $viewerPrivateKey, 'GET', '/api/reported-escorts-summary', $summaryTimestamp, $summaryNonce);

        $response = $this->call('GET', '/api/reported-escorts-summary', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$viewerToken,
            'HTTP_X_HARDWARE_NONCE' => $summaryNonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $summaryTimestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $summarySignature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk();
        $response->assertJsonPath('meta.can_view_reports', true);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.flat_id', $reportedFlat->id);
        $response->assertJsonPath('data.0.flat_title', 'Reported Flat');
        $response->assertJsonPath('data.0.escort_id', $escort->id);
        $response->assertJsonPath('data.0.escort_external_id', 29639);
        $response->assertJsonPath('data.0.phone_number', '+421900111557');
        $response->assertJsonPath('data.0.reason_code', LandlordReportReason::Drugs->value);

        $indexTimestamp = (string) now()->timestamp;
        $indexNonce = (string) Str::uuid();
        $indexSignature = $this->signHardwareContent('', $viewerPrivateKey, 'GET', '/api/flats', $indexTimestamp, $indexNonce);

        $indexResponse = $this->call('GET', '/api/flats', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$viewerToken,
            'HTTP_X_HARDWARE_NONCE' => $indexNonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $indexTimestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $indexSignature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $indexResponse->assertOk();
        $indexResponse->assertJsonCount(1, 'reported_escorts_summary');
        $indexResponse->assertJsonPath('reported_escorts_summary.0.flat_id', $reportedFlat->id);
        $indexResponse->assertJsonPath('reported_escorts_summary.0.escort_external_id', 29639);
    }

    #[Test]
    public function landlord_without_published_flats_cannot_view_reported_escort_summary(): void
    {
        [$privateKey, $token] = $this->createLandlordSession();

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'GET', '/api/reported-escorts-summary', $timestamp, $nonce);

        $response = $this->call('GET', '/api/reported-escorts-summary', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertSame([], $data['data']);
        $this->assertSame(false, $data['meta']['can_view_reports']);
        $this->assertSame('Publish at least one flat before reviewing reported escorts.', $data['meta']['message']);
    }

    #[Test]
    public function landlord_can_create_flat_with_contact_shortcuts(): void
    {
        [$privateKey, $token, $landlord] = $this->createLandlordSession();

        $payload = [
            'title' => 'Contact Flat',
            'description' => 'Has real contact shortcuts.',
            'contact' => [
                'phone' => '+421900333444',
                'email' => 'contact@example.test',
                'whatsapp_url' => 'https://wa.me/421900333444',
                'telegram_url' => 'https://t.me/contact_example',
                'viber_url' => 'viber://chat?number=%2B421900333444',
            ],
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats', $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->postJson('/api/flats', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.contact.phone', '+421900333444')
            ->assertJsonPath('data.contact.email', 'contact@example.test');

        $this->assertDatabaseHas('flats', [
            'landlord_id' => $landlord->id,
            'title' => 'Contact Flat',
            'contact_phone' => '+421900333444',
            'contact_email' => 'contact@example.test',
            'whatsapp_url' => 'https://wa.me/421900333444',
            'telegram_url' => 'https://t.me/contact_example',
            'viber_url' => 'viber://chat?number=%2B421900333444',
        ]);
    }

    #[Test]
    public function landlord_flat_creation_requires_description_phone_and_email(): void
    {
        [$privateKey, $token] = $this->createLandlordSession();

        $payload = [
            'title' => 'Incomplete Flat',
            'contact' => [],
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats', $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->postJson('/api/flats', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'description',
                'contact.phone',
                'contact.email',
            ]);
    }

    #[Test]
    public function guest_vote_is_hardware_signed(): void
    {
        [$privateKey, $escort, $token] = $this->createEscortSession('+421900111333');
        $landlord = Landlord::query()->create(['is_verified' => true]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Voting Flat',
            'description' => 'Guest can vote here.',
        ]);

        Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => $escort->phone_number,
            'otp_token' => hash('sha256', '3003'),
            'status' => InvitationStatus::Accepted,
            'expires_at' => now()->addMinutes(10),
        ]);

        $payload = ['is_favorite' => true];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats/'.$flat->id.'/vote', $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->postJson('/api/flats/'.$flat->id.'/vote', $payload);

        $response->assertOk()->assertJsonPath('data.is_favorite', true);
        $this->assertDatabaseHas('votes', [
            'flat_id' => $flat->id,
            'escort_id' => $escort->id,
            'is_favorite' => true,
        ]);
    }

    #[Test]
    public function gallery_votes_count_only_includes_favorite_votes(): void
    {
        [$privateKey, $escort, $token] = $this->createEscortSession('+421900111334');
        $landlord = Landlord::query()->create(['is_verified' => true]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Favorite Count Flat',
            'description' => 'Only true favorites should be counted.',
        ]);

        Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => $escort->phone_number,
            'otp_token' => hash('sha256', '3004'),
            'status' => InvitationStatus::Accepted,
            'expires_at' => now()->addMinutes(10),
        ]);

        $otherEscortA = Escort::query()->create([
            'external_id' => 40001,
            'phone_number' => '+421900111701',
            'public_key' => $this->generateEcKeyPair()[1],
        ]);
        $otherEscortB = Escort::query()->create([
            'external_id' => 40002,
            'phone_number' => '+421900111702',
            'public_key' => $this->generateEcKeyPair()[1],
        ]);

        $flat->votes()->create([
            'escort_id' => $escort->id,
            'is_favorite' => true,
        ]);
        $flat->votes()->create([
            'escort_id' => $otherEscortA->id,
            'is_favorite' => true,
        ]);
        $flat->votes()->create([
            'escort_id' => $otherEscortB->id,
            'is_favorite' => false,
        ]);

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'GET', '/api/flats', $timestamp, $nonce);

        $response = $this->call('GET', '/api/flats', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.0.votes_count', 2);
    }

    #[Test]
    public function favorite_votes_cannot_exceed_ten_per_flat(): void
    {
        [$privateKey, $escort, $token] = $this->createEscortSession('+421900111335');
        $landlord = Landlord::query()->create(['is_verified' => true]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Capped Favorite Flat',
            'description' => 'The eleventh favorite should be rejected.',
        ]);

        Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => $escort->phone_number,
            'otp_token' => hash('sha256', '3005'),
            'status' => InvitationStatus::Accepted,
            'expires_at' => now()->addMinutes(10),
        ]);

        for ($index = 0; $index < 10; $index++) {
            $flat->votes()->create([
                'escort_id' => Escort::query()->create([
                    'external_id' => 50000 + $index,
                    'phone_number' => sprintf('+421900112%03d', $index),
                    'public_key' => $this->generateEcKeyPair()[1],
                ])->id,
                'is_favorite' => true,
            ]);
        }

        $payload = ['is_favorite' => true];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats/'.$flat->id.'/vote', $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->postJson('/api/flats/'.$flat->id.'/vote', $payload);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'This flat already reached the maximum of 10 likes.');
        $this->assertSame(10, $flat->fresh()->votes()->where('is_favorite', true)->count());
    }

    #[Test]
    public function multipart_upload_signature_contract(): void
    {
        Storage::fake('public');

        [$privateKey, $token, $landlord] = $this->createLandlordSession();
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Upload Flat',
            'description' => 'Ready for photos.',
        ]);

        $file = UploadedFile::fake()->image('flat.jpg');
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'POST', '/api/flats/'.$flat->id.'/photos', $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
                'Accept' => 'application/json',
            ])
            ->post('/api/flats/'.$flat->id.'/photos', [
                'photo' => $file,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('flat_photos', [
            'flat_id' => $flat->id,
            'original_filename' => 'flat.jpg',
        ]);

        $storedPhoto = $flat->photos()->firstOrFail();
        $this->assertTrue(Storage::disk('public')->exists($storedPhoto->storage_path));
        $response->assertJsonMissingPath('data.storage_disk');
        $response->assertJsonMissingPath('data.storage_path');
    }

    #[Test]
    public function photo_content_route_is_publicly_readable(): void
    {
        Storage::fake('public');

        $landlord = Landlord::query()->create(['is_verified' => true]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Visible Flat',
            'description' => 'Anyone can read photo content.',
        ]);

        Storage::disk('public')->put('flat-photos/content.jpg', 'photo-content');
        $photo = $flat->photos()->create([
            'storage_disk' => 'public',
            'storage_path' => 'flat-photos/content.jpg',
            'original_filename' => 'content.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 13,
            'sort_order' => 1,
        ]);

        $response = $this->get('/api/photos/'.$photo->id.'/content');

        $response->assertOk();
        $this->assertSame('no-store, private', $response->headers->get('cache-control'));
    }

    #[Test]
    public function photo_content_route_returns_not_found_when_file_is_missing(): void
    {
        Storage::fake('public');

        $landlord = Landlord::query()->create(['is_verified' => true]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Hidden Flat',
            'description' => 'Missing file should fail cleanly.',
        ]);

        $photo = $flat->photos()->create([
            'storage_disk' => 'public',
            'storage_path' => 'flat-photos/hidden.jpg',
            'original_filename' => 'hidden.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 14,
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/photos/'.$photo->id.'/content');

        $response->assertNotFound()->assertJsonPath('message', 'Photo file not found.');
    }

    #[Test]
    public function verified_landlord_can_fetch_foreign_photo_content(): void
    {
        Storage::fake('public');

        [$privateKey, $token] = $this->createLandlordSession();
        $foreignLandlord = Landlord::query()->create(['is_verified' => true]);
        $flat = Flat::query()->create([
            'landlord_id' => $foreignLandlord->id,
            'title' => 'Foreign Flat',
            'description' => 'Visible to verified landlords.',
        ]);

        Storage::disk('public')->put('flat-photos/foreign.jpg', 'foreign-photo-content');
        $photo = $flat->photos()->create([
            'storage_disk' => 'public',
            'storage_path' => 'flat-photos/foreign.jpg',
            'original_filename' => 'foreign.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 21,
            'sort_order' => 1,
        ]);

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'GET', '/api/photos/'.$photo->id.'/content', $timestamp, $nonce);

        $response = $this->call('GET', '/api/photos/'.$photo->id.'/content', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
        ]);

        $response->assertOk();
    }

    #[Test]
    public function landlord_can_delete_owned_photo_and_file_is_removed(): void
    {
        Storage::fake('public');

        [$privateKey, $token, $landlord] = $this->createLandlordSession();
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Delete Flat',
            'description' => 'Photo removal target.',
        ]);

        Storage::disk('public')->put('flat-photos/delete-me.jpg', 'photo-bytes');
        $photo = $flat->photos()->create([
            'storage_disk' => 'public',
            'storage_path' => 'flat-photos/delete-me.jpg',
            'original_filename' => 'delete-me.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 10,
            'sort_order' => 1,
        ]);

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'DELETE', '/api/photos/'.$photo->id, $timestamp, $nonce);

        $response = $this->call('DELETE', '/api/photos/'.$photo->id, [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertNoContent();
        $this->assertDatabaseMissing('flat_photos', ['id' => $photo->id]);
        $this->assertFalse(Storage::disk('public')->exists('flat-photos/delete-me.jpg'));
    }

    #[Test]
    public function landlord_cannot_delete_another_landlords_photo(): void
    {
        Storage::fake('public');

        [$privateKey, $token] = $this->createLandlordSession();
        $otherLandlord = Landlord::query()->create(['is_verified' => true]);
        $flat = Flat::query()->create([
            'landlord_id' => $otherLandlord->id,
            'title' => 'Foreign Flat',
            'description' => 'Must stay protected.',
        ]);

        Storage::disk('public')->put('flat-photos/foreign.jpg', 'photo-bytes');
        $photo = $flat->photos()->create([
            'storage_disk' => 'public',
            'storage_path' => 'flat-photos/foreign.jpg',
            'original_filename' => 'foreign.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 10,
            'sort_order' => 1,
        ]);

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'DELETE', '/api/photos/'.$photo->id, $timestamp, $nonce);

        $response = $this->call('DELETE', '/api/photos/'.$photo->id, [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertForbidden()->assertJsonPath('message', 'You do not own this photo.');
        $this->assertDatabaseHas('flat_photos', ['id' => $photo->id]);
        $this->assertTrue(Storage::disk('public')->exists('flat-photos/foreign.jpg'));
    }

    #[Test]
    public function escort_can_report_landlord_with_fixed_reason_codes(): void
    {
        [$privateKey, $escort, $token] = $this->createEscortSession('+421900111446');
        $landlord = Landlord::query()->create(['is_verified' => true]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Report Flat',
            'description' => 'Escort can report the landlord.',
        ]);

        Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => $escort->phone_number,
            'otp_token' => hash('sha256', '4466'),
            'status' => InvitationStatus::Accepted,
            'expires_at' => now()->addMinutes(10),
        ]);

        $payload = ['reason_code' => EscortReportReason::Harassing->value];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats/'.$flat->id.'/report-landlord', $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->postJson('/api/flats/'.$flat->id.'/report-landlord', $payload);

        $response->assertCreated()->assertJsonPath('data.reason_code', EscortReportReason::Harassing->value);
        $this->assertDatabaseHas('flat_reports', [
            'flat_id' => $flat->id,
            'reporter_escort_id' => $escort->id,
            'reported_landlord_id' => $landlord->id,
            'reason_code' => EscortReportReason::Harassing->value,
        ]);
    }

    #[Test]
    public function escort_can_report_the_same_landlord_for_multiple_distinct_reasons(): void
    {
        [$privateKey, $escort, $token] = $this->createEscortSession('+421900111448');
        $landlord = Landlord::query()->create(['is_verified' => true]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Multi Reason Escort Report Flat',
            'description' => 'Distinct escort landlord reports should coexist.',
        ]);

        Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => $escort->phone_number,
            'otp_token' => hash('sha256', '4488'),
            'status' => InvitationStatus::Accepted,
            'expires_at' => now()->addMinutes(10),
        ]);

        FlatReport::query()->create([
            'flat_id' => $flat->id,
            'reporter_escort_id' => $escort->id,
            'reported_landlord_id' => $landlord->id,
            'reason_code' => EscortReportReason::Pimp->value,
        ]);

        $payload = ['reason_code' => EscortReportReason::DidNotKeepAgreement->value];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats/'.$flat->id.'/report-landlord', $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->postJson('/api/flats/'.$flat->id.'/report-landlord', $payload);

        $response->assertCreated()->assertJsonPath('data.reason_code', EscortReportReason::DidNotKeepAgreement->value);
        $this->assertDatabaseHas('flat_reports', [
            'flat_id' => $flat->id,
            'reporter_escort_id' => $escort->id,
            'reported_landlord_id' => $landlord->id,
            'reason_code' => EscortReportReason::Pimp->value,
        ]);
        $this->assertDatabaseHas('flat_reports', [
            'flat_id' => $flat->id,
            'reporter_escort_id' => $escort->id,
            'reported_landlord_id' => $landlord->id,
            'reason_code' => EscortReportReason::DidNotKeepAgreement->value,
        ]);
        $this->assertDatabaseCount('flat_reports', 2);
    }

    #[Test]
    public function escort_cannot_report_the_same_landlord_for_the_same_reason_twice(): void
    {
        [$privateKey, $escort, $token] = $this->createEscortSession('+421900111449');
        $landlord = Landlord::query()->create(['is_verified' => true]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Duplicate Escort Report Flat',
            'description' => 'Duplicate escort landlord reports should be rejected.',
        ]);

        Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => $escort->phone_number,
            'otp_token' => hash('sha256', '4499'),
            'status' => InvitationStatus::Accepted,
            'expires_at' => now()->addMinutes(10),
        ]);

        FlatReport::query()->create([
            'flat_id' => $flat->id,
            'reporter_escort_id' => $escort->id,
            'reported_landlord_id' => $landlord->id,
            'reason_code' => EscortReportReason::Harassing->value,
        ]);

        $payload = ['reason_code' => EscortReportReason::Harassing->value];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats/'.$flat->id.'/report-landlord', $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->postJson('/api/flats/'.$flat->id.'/report-landlord', $payload);

        $response
            ->assertConflict()
            ->assertJsonPath('message', 'You already reported this landlord for harassing.');
        $this->assertDatabaseCount('flat_reports', 1);
    }

    #[Test]
    public function escort_flat_gallery_includes_their_saved_landlord_report_reason(): void
    {
        [$privateKey, $escort, $token] = $this->createEscortSession('+421900111460');
        $landlord = Landlord::query()->create(['is_verified' => true]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Reported Flat',
            'description' => 'Escort should see their saved landlord report.',
        ]);

        Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => $escort->phone_number,
            'otp_token' => hash('sha256', '4600'),
            'status' => InvitationStatus::Accepted,
            'expires_at' => now()->addMinutes(10),
        ]);

        FlatReport::query()->create([
            'flat_id' => $flat->id,
            'reporter_escort_id' => $escort->id,
            'reported_landlord_id' => $landlord->id,
            'reason_code' => EscortReportReason::DidNotKeepAgreement->value,
        ]);

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'GET', '/api/flats', $timestamp, $nonce);

        $response = $this->call('GET', '/api/flats', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.id', $flat->id)
            ->assertJsonPath('data.0.landlord_reports_count', 1)
            ->assertJsonPath('data.0.landlord_report_reasons.0', EscortReportReason::DidNotKeepAgreement->value)
            ->assertJsonPath('data.0.my_landlord_report_reasons.0', EscortReportReason::DidNotKeepAgreement->value);
    }

    #[Test]
    public function escort_flat_gallery_preserves_all_landlord_report_rows(): void
    {
        [$privateKey, $escort, $token] = $this->createEscortSession('+421900111461');
        $landlord = Landlord::query()->create(['is_verified' => true]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Repeated Report Flat',
            'description' => 'Gallery should expose every landlord report row.',
        ]);

        Invitation::query()->create([
            'landlord_id' => $landlord->id,
            'phone_number' => $escort->phone_number,
            'otp_token' => hash('sha256', '4610'),
            'status' => InvitationStatus::Accepted,
            'expires_at' => now()->addMinutes(10),
        ]);

        $otherEscort = Escort::query()->create([
            'external_id' => 29640,
            'phone_number' => '+421900111462',
            'public_key' => $this->generateEcKeyPair()[1],
        ]);

        FlatReport::query()->create([
            'flat_id' => $flat->id,
            'reporter_escort_id' => $escort->id,
            'reported_landlord_id' => $landlord->id,
            'reason_code' => EscortReportReason::DidNotKeepAgreement->value,
            'updated_at' => now()->subMinute(),
        ]);

        FlatReport::query()->create([
            'flat_id' => $flat->id,
            'reporter_escort_id' => $otherEscort->id,
            'reported_landlord_id' => $landlord->id,
            'reason_code' => EscortReportReason::DidNotKeepAgreement->value,
            'updated_at' => now(),
        ]);

        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareContent('', $privateKey, 'GET', '/api/flats', $timestamp, $nonce);

        $response = $this->call('GET', '/api/flats', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_HARDWARE_NONCE' => $nonce,
            'HTTP_X_HARDWARE_TIMESTAMP' => $timestamp,
            'HTTP_X_HARDWARE_SIGNATURE' => $signature,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.id', $flat->id)
            ->assertJsonPath('data.0.landlord_reports_count', 2)
            ->assertJsonCount(2, 'data.0.landlord_report_reasons')
            ->assertJsonPath('data.0.landlord_report_reasons.0', EscortReportReason::DidNotKeepAgreement->value)
            ->assertJsonPath('data.0.landlord_report_reasons.1', EscortReportReason::DidNotKeepAgreement->value);
    }

    #[Test]
    public function landlord_can_report_escort_with_fixed_reason_codes(): void
    {
        [$privateKey, $token, $landlord] = $this->createLandlordSession();
        $escort = Escort::query()->create([
            'external_id' => 29639,
            'phone_number' => '+421900111447',
            'public_key' => $this->generateEcKeyPair()[1],
        ]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Landlord Report Flat',
            'description' => 'Landlord can report the escort.',
        ]);

        $payload = [
            'escort_external_id' => 29639,
            'reason_code' => LandlordReportReason::DidNotPay->value,
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats/'.$flat->id.'/report-escort', $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->postJson('/api/flats/'.$flat->id.'/report-escort', $payload);

        $response->assertCreated()->assertJsonPath('data.reason_code', LandlordReportReason::DidNotPay->value);
        $this->assertDatabaseHas('flat_reports', [
            'flat_id' => $flat->id,
            'reporter_landlord_id' => $landlord->id,
            'reported_escort_id' => $escort->id,
            'reported_escort_external_id' => 29639,
            'reason_code' => LandlordReportReason::DidNotPay->value,
        ]);
    }

    #[Test]
    public function landlord_can_report_escort_for_a_fake_listing(): void
    {
        [$privateKey, $token, $landlord] = $this->createLandlordSession();
        $escort = Escort::query()->create([
            'external_id' => 29642,
            'phone_number' => '+421900111451',
            'public_key' => $this->generateEcKeyPair()[1],
        ]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Fraud Report Flat',
            'description' => 'Landlord can report a fake listing.',
        ]);

        $payload = [
            'escort_external_id' => 29642,
            'reason_code' => LandlordReportReason::FakeListing->value,
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats/'.$flat->id.'/report-escort', $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->postJson('/api/flats/'.$flat->id.'/report-escort', $payload);

        $response->assertCreated()->assertJsonPath('data.reason_code', LandlordReportReason::FakeListing->value);
        $this->assertDatabaseHas('flat_reports', [
            'flat_id' => $flat->id,
            'reporter_landlord_id' => $landlord->id,
            'reported_escort_id' => $escort->id,
            'reported_escort_external_id' => 29642,
            'reason_code' => LandlordReportReason::FakeListing->value,
        ]);
    }

    #[Test]
    public function landlord_can_record_multiple_escort_report_reasons_for_the_same_escort(): void
    {
        [$privateKey, $token, $landlord] = $this->createLandlordSession();
        [, $escortPublicKey] = $this->generateEcKeyPair();
        $escort = Escort::query()->create([
            'external_id' => 29640,
            'phone_number' => '+421900111449',
            'public_key' => $escortPublicKey,
        ]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Editable Landlord Report Flat',
            'description' => 'Landlord report should update in place.',
        ]);

        FlatReport::query()->create([
            'flat_id' => $flat->id,
            'reporter_landlord_id' => $landlord->id,
            'reported_escort_id' => $escort->id,
            'reported_escort_external_id' => 29640,
            'reason_code' => LandlordReportReason::Drugs->value,
        ]);

        $payload = [
            'escort_external_id' => 29640,
            'reason_code' => LandlordReportReason::Hygiene->value,
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats/'.$flat->id.'/report-escort', $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->postJson('/api/flats/'.$flat->id.'/report-escort', $payload);

        $response->assertCreated()->assertJsonPath('data.reason_code', LandlordReportReason::Hygiene->value);
        $this->assertDatabaseHas('flat_reports', [
            'flat_id' => $flat->id,
            'reporter_landlord_id' => $landlord->id,
            'reported_escort_id' => $escort->id,
            'reported_escort_external_id' => 29640,
            'reason_code' => LandlordReportReason::Drugs->value,
        ]);
        $this->assertDatabaseHas('flat_reports', [
            'flat_id' => $flat->id,
            'reporter_landlord_id' => $landlord->id,
            'reported_escort_id' => $escort->id,
            'reported_escort_external_id' => 29640,
            'reason_code' => LandlordReportReason::Hygiene->value,
        ]);
        $this->assertDatabaseCount('flat_reports', 2);
    }

    #[Test]
    public function landlord_cannot_report_the_same_escort_for_the_same_reason_twice(): void
    {
        [$privateKey, $token, $landlord] = $this->createLandlordSession();
        [, $escortPublicKey] = $this->generateEcKeyPair();
        $escort = Escort::query()->create([
            'external_id' => 29641,
            'phone_number' => '+421900111450',
            'public_key' => $escortPublicKey,
        ]);
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Duplicate Landlord Report Flat',
            'description' => 'Duplicate landlord reports should be rejected.',
        ]);

        FlatReport::query()->create([
            'flat_id' => $flat->id,
            'reporter_landlord_id' => $landlord->id,
            'reported_escort_id' => $escort->id,
            'reported_escort_external_id' => 29641,
            'reason_code' => LandlordReportReason::Drugs->value,
        ]);

        $payload = [
            'escort_external_id' => 29641,
            'reason_code' => LandlordReportReason::Drugs->value,
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats/'.$flat->id.'/report-escort', $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->postJson('/api/flats/'.$flat->id.'/report-escort', $payload);

        $response
            ->assertConflict()
            ->assertJsonPath('message', 'You already reported this escort for drugs.');
        $this->assertDatabaseCount('flat_reports', 1);
    }

    #[Test]
    public function landlord_can_report_unregistered_escort_by_external_ad_id(): void
    {
        [$privateKey, $token, $landlord] = $this->createLandlordSession();
        $flat = Flat::query()->create([
            'landlord_id' => $landlord->id,
            'title' => 'Decoupled Landlord Report Flat',
            'description' => 'Reporting no longer depends on escort app registration.',
        ]);

        $payload = [
            'escort_external_id' => 29642,
            'reason_code' => LandlordReportReason::Drugs->value,
        ];
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $signature = $this->signHardwareRequest($payload, $privateKey, 'POST', '/api/flats/'.$flat->id.'/report-escort', $timestamp, $nonce);

        $response = $this->withToken($token)
            ->withHeaders([
                'X-Hardware-Nonce' => $nonce,
                'X-Hardware-Timestamp' => $timestamp,
                'X-Hardware-Signature' => $signature,
            ])
            ->postJson('/api/flats/'.$flat->id.'/report-escort', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('data.reported_escort_id', null)
            ->assertJsonPath('data.reported_escort_external_id', 29642);
        $this->assertDatabaseHas('flat_reports', [
            'flat_id' => $flat->id,
            'reporter_landlord_id' => $landlord->id,
            'reported_escort_external_id' => 29642,
            'reason_code' => LandlordReportReason::Drugs->value,
        ]);
    }

    private function createEscortSession(string $phoneNumber): array
    {
        [$privateKey, $publicKey] = $this->generateEcKeyPair();
        $escort = Escort::query()->create([
            'phone_number' => $phoneNumber,
            'public_key' => $publicKey,
        ]);

        return [$privateKey, $escort, $escort->createToken('escort-device')->plainTextToken];
    }

    private function createLandlordSession(): array
    {
        [$privateKey, $publicKey] = $this->generateEcKeyPair();
        $landlord = Landlord::query()->create([
            'public_key' => $publicKey,
            'is_verified' => true,
        ]);

        return [$privateKey, $landlord->createToken('landlord-device')->plainTextToken, $landlord];
    }
}
