<?php

namespace App\Console\Commands;

use App\Models\Landlord;
use App\Models\Escort;
use App\Models\Municipality;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'dev:sync-production-flats',
    description: 'Replace the local flat snapshot with data imported from a configured production database connection',
)]
class SyncProductionFlatsCommand extends Command
{
    protected $signature = 'dev:sync-production-flats
        {--copy-photos : Copy flat photo binaries from PROD_SYNC_FLAT_PHOTO_ROOT}
        {--chunk=250 : Number of remote flats to process per batch}';

    protected $description = 'Replace the local flat snapshot with data imported from a configured production database connection';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('This command is for local development only.');

            return self::FAILURE;
        }

        $connectionName = 'production_sync';
        $remoteConnectionConfig = $this->buildProductionSyncConnectionConfig();

        if ($remoteConnectionConfig === null) {
            $this->error('Set PROD_SYNC_DB_DRIVER plus PROD_SYNC_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD or PROD_SYNC_DB_URL before syncing.');

            return self::FAILURE;
        }

        Config::set("database.connections.{$connectionName}", $remoteConnectionConfig);
        DB::purge($connectionName);

        try {
            $remoteDb = DB::connection($connectionName);
            $remoteDb->getPdo();
        } catch (\Throwable $error) {
            $this->error('Production sync connection failed: '.$error->getMessage());

            return self::FAILURE;
        }

        $photoRoot = env('PROD_SYNC_FLAT_PHOTO_ROOT');
        $shouldCopyPhotos = (bool) $this->option('copy-photos');

        if ($shouldCopyPhotos && (! is_string($photoRoot) || trim($photoRoot) === '')) {
            $this->error('Set PROD_SYNC_FLAT_PHOTO_ROOT before using --copy-photos.');

            return self::FAILURE;
        }

        $missingTables = collect([
            'escorts',
            'landlords',
            'flats',
            'flat_photos',
            'votes',
            'flat_reports',
            'municipalities',
        ])->filter(fn (string $table): bool => ! Schema::hasTable($table));

        if ($missingTables->isNotEmpty()) {
            $this->error('Local schema is missing required tables: '.$missingTables->implode(', '));
            $this->comment('Run php artisan migrate against the local database first, then rerun dev:sync-production-flats.');

            return self::FAILURE;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));

        try {
            $summary = DB::transaction(function () use ($chunkSize, $photoRoot, $remoteDb, $shouldCopyPhotos): array {
                $localMunicipalityIdsByKey = Municipality::query()
                    ->get(['id', 'name', 'district', 'region'])
                    ->mapWithKeys(fn (Municipality $municipality): array => [
                        $this->buildMunicipalitySyncKey(
                            $municipality->name,
                            $municipality->district,
                            $municipality->region,
                        ) => $municipality->id,
                    ]);
                $remoteMunicipalitiesById = $remoteDb->table('municipalities')
                    ->get(['id', 'name', 'district', 'region'])
                    ->keyBy('id');
                $remoteEscortsById = $remoteDb->table('escorts')
                    ->get(['id', 'external_id', 'phone_number'])
                    ->keyBy('id');
                $remoteLandlordsById = $remoteDb->table('landlords')
                    ->get(['id'])
                    ->keyBy('id');
                $remoteToLocalEscortIds = [];
                $remoteToLocalLandlordIds = [];
                $summary = [
                    'copiedPhotoCount' => 0,
                    'importedReportCount' => 0,
                    'skippedPhotoCount' => 0,
                    'importedFlatCount' => 0,
                    'importedVoteCount' => 0,
                ];
                $photoCopyRoot = is_string($photoRoot) ? rtrim($photoRoot, DIRECTORY_SEPARATOR) : null;

                DB::table('flat_reports')->delete();
                DB::table('votes')->delete();
                DB::table('flat_photos')->delete();
                DB::table('flats')->delete();

                $remoteDb->table('flats')
                    ->orderBy('id')
                    ->chunk($chunkSize, function (Collection $remoteFlats) use (
                        &$remoteToLocalEscortIds,
                        &$remoteToLocalLandlordIds,
                        &$summary,
                        $localMunicipalityIdsByKey,
                        $photoCopyRoot,
                        $remoteDb,
                        $remoteEscortsById,
                        $remoteLandlordsById,
                        $remoteMunicipalitiesById,
                        $shouldCopyPhotos,
                    ): void {
                        $remoteFlatIds = $remoteFlats->pluck('id')->all();
                        $remotePhotosByFlatId = $shouldCopyPhotos
                            ? $remoteDb->table('flat_photos')
                                ->whereIn('flat_id', $remoteFlatIds)
                                ->orderBy('flat_id')
                                ->orderBy('sort_order')
                                ->get([
                                    'flat_id',
                                    'storage_disk',
                                    'storage_path',
                                    'original_filename',
                                    'mime_type',
                                    'byte_size',
                                    'sort_order',
                                ])
                                ->groupBy('flat_id')
                            : collect();
                        $remoteReportsByFlatId = $remoteDb->table('flat_reports')
                            ->whereIn('flat_id', $remoteFlatIds)
                            ->orderBy('flat_id')
                            ->orderBy('id')
                            ->get([
                                'flat_id',
                                'reporter_landlord_id',
                                'reporter_escort_id',
                                'reported_landlord_id',
                                'reported_escort_id',
                                'reported_escort_external_id',
                                'reason_code',
                                'created_at',
                                'updated_at',
                            ])
                            ->groupBy('flat_id');
                        $remoteVotesByFlatId = $remoteDb->table('votes')
                            ->whereIn('flat_id', $remoteFlatIds)
                            ->orderBy('flat_id')
                            ->orderBy('id')
                            ->get([
                                'flat_id',
                                'escort_id',
                                'is_favorite',
                                'created_at',
                                'updated_at',
                            ])
                            ->groupBy('flat_id');

                        foreach ($remoteFlats as $remoteFlat) {
                            $remoteLandlord = $remoteLandlordsById->get($remoteFlat->landlord_id);

                            if ($remoteLandlord === null) {
                                continue;
                            }

                            $localLandlordId = $remoteToLocalLandlordIds[$remoteFlat->landlord_id]
                                ??= $this->resolveLocalSnapshotLandlordId((int) $remoteLandlord->id);
                            $remoteMunicipality = $remoteMunicipalitiesById->get($remoteFlat->municipality_id);
                            $localMunicipalityId = null;

                            if ($remoteMunicipality !== null) {
                                $localMunicipalityId = $localMunicipalityIdsByKey->get(
                                    $this->buildMunicipalitySyncKey(
                                        (string) $remoteMunicipality->name,
                                        (string) $remoteMunicipality->district,
                                        (string) $remoteMunicipality->region,
                                    ),
                                );
                            }

                            $localFlatId = DB::table('flats')->insertGetId([
                                'landlord_id' => $localLandlordId,
                                'municipality_id' => $localMunicipalityId,
                                'title' => $remoteFlat->title,
                                'description' => $remoteFlat->description,
                                'contact_phone' => $remoteFlat->contact_phone,
                                'contact_email' => $remoteFlat->contact_email,
                                'whatsapp_url' => $remoteFlat->whatsapp_url,
                                'telegram_url' => $remoteFlat->telegram_url,
                                'viber_url' => $remoteFlat->viber_url,
                                'created_at' => $remoteFlat->created_at ?? now(),
                                'updated_at' => $remoteFlat->updated_at ?? now(),
                            ]);
                            $summary['importedFlatCount']++;

                            foreach ($remoteVotesByFlatId->get($remoteFlat->id, collect()) as $remoteVote) {
                                $remoteEscort = $remoteEscortsById->get($remoteVote->escort_id);

                                if ($remoteEscort === null) {
                                    continue;
                                }

                                $localEscortId = $remoteToLocalEscortIds[$remoteVote->escort_id]
                                    ??= $this->resolveLocalSnapshotEscortId(
                                        (string) $remoteEscort->phone_number,
                                        $remoteEscort->external_id !== null
                                            ? (int) $remoteEscort->external_id
                                            : null,
                                    );

                                DB::table('votes')->insert([
                                    'flat_id' => $localFlatId,
                                    'escort_id' => $localEscortId,
                                    'is_favorite' => (bool) $remoteVote->is_favorite,
                                    'created_at' => $remoteVote->created_at ?? now(),
                                    'updated_at' => $remoteVote->updated_at ?? now(),
                                ]);
                                $summary['importedVoteCount']++;
                            }

                            foreach ($remoteReportsByFlatId->get($remoteFlat->id, collect()) as $remoteReport) {
                                $reporterLandlordId = $remoteReport->reporter_landlord_id !== null
                                    ? $remoteToLocalLandlordIds[$remoteReport->reporter_landlord_id]
                                        ??= $this->resolveLocalSnapshotLandlordId((int) $remoteReport->reporter_landlord_id)
                                    : null;
                                $reportedLandlordId = $remoteReport->reported_landlord_id !== null
                                    ? $remoteToLocalLandlordIds[$remoteReport->reported_landlord_id]
                                        ??= $this->resolveLocalSnapshotLandlordId((int) $remoteReport->reported_landlord_id)
                                    : null;
                                $reporterEscortId = null;
                                $reportedEscortId = null;

                                if ($remoteReport->reporter_escort_id !== null) {
                                    $remoteReporterEscort = $remoteEscortsById->get($remoteReport->reporter_escort_id);

                                    if ($remoteReporterEscort !== null) {
                                        $reporterEscortId = $remoteToLocalEscortIds[$remoteReport->reporter_escort_id]
                                            ??= $this->resolveLocalSnapshotEscortId(
                                                (string) $remoteReporterEscort->phone_number,
                                                $remoteReporterEscort->external_id !== null
                                                    ? (int) $remoteReporterEscort->external_id
                                                    : null,
                                            );
                                    }
                                }

                                if ($remoteReport->reported_escort_id !== null) {
                                    $remoteReportedEscort = $remoteEscortsById->get($remoteReport->reported_escort_id);

                                    if ($remoteReportedEscort !== null) {
                                        $reportedEscortId = $remoteToLocalEscortIds[$remoteReport->reported_escort_id]
                                            ??= $this->resolveLocalSnapshotEscortId(
                                                (string) $remoteReportedEscort->phone_number,
                                                $remoteReportedEscort->external_id !== null
                                                    ? (int) $remoteReportedEscort->external_id
                                                    : null,
                                            );
                                    }
                                }

                                DB::table('flat_reports')->insert([
                                    'flat_id' => $localFlatId,
                                    'reporter_landlord_id' => $reporterLandlordId,
                                    'reporter_escort_id' => $reporterEscortId,
                                    'reported_landlord_id' => $reportedLandlordId,
                                    'reported_escort_id' => $reportedEscortId,
                                    'reported_escort_external_id' => $remoteReport->reported_escort_external_id,
                                    'reason_code' => $remoteReport->reason_code,
                                    'created_at' => $remoteReport->created_at ?? now(),
                                    'updated_at' => $remoteReport->updated_at ?? now(),
                                ]);
                                $summary['importedReportCount']++;
                            }

                            if (! $shouldCopyPhotos) {
                                continue;
                            }

                            foreach ($remotePhotosByFlatId->get($remoteFlat->id, collect()) as $remotePhoto) {
                                $sourcePath = $photoCopyRoot.DIRECTORY_SEPARATOR.ltrim((string) $remotePhoto->storage_path, DIRECTORY_SEPARATOR);

                                if (! is_string($photoCopyRoot) || ! is_file($sourcePath)) {
                                    $summary['skippedPhotoCount']++;
                                    continue;
                                }

                                Storage::disk((string) $remotePhoto->storage_disk)->put(
                                    (string) $remotePhoto->storage_path,
                                    file_get_contents($sourcePath),
                                );

                                DB::table('flat_photos')->insert([
                                    'flat_id' => $localFlatId,
                                    'storage_disk' => $remotePhoto->storage_disk,
                                    'storage_path' => $remotePhoto->storage_path,
                                    'original_filename' => $remotePhoto->original_filename,
                                    'mime_type' => $remotePhoto->mime_type,
                                    'byte_size' => $remotePhoto->byte_size,
                                    'sort_order' => $remotePhoto->sort_order,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                                $summary['copiedPhotoCount']++;
                            }
                        }
                    });

                return $summary;
            });
        } finally {
            DB::disconnect($connectionName);
        }

        $this->info("Imported {$summary['importedFlatCount']} flat snapshots into the local database.");
        $this->info("Imported {$summary['importedVoteCount']} votes and {$summary['importedReportCount']} flat reports.");

        if ($shouldCopyPhotos) {
            $this->info("Copied {$summary['copiedPhotoCount']} photos and skipped {$summary['skippedPhotoCount']} missing photo files.");
        } else {
            $this->comment('Photo binaries were not copied. Re-run with --copy-photos and PROD_SYNC_FLAT_PHOTO_ROOT set to a mounted production flat-photo directory if you need local images.');
        }

        return self::SUCCESS;
    }

    private function buildProductionSyncConnectionConfig(): ?array
    {
        $databaseUrl = env('PROD_SYNC_DB_URL');
        $driver = env('PROD_SYNC_DB_DRIVER');

        if (is_string($databaseUrl) && trim($databaseUrl) !== '') {
            return [
                'driver' => $driver ?: 'pgsql',
                'url' => $databaseUrl,
                'charset' => env('PROD_SYNC_DB_CHARSET', 'utf8'),
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => env('PROD_SYNC_DB_SCHEMA', 'public'),
                'sslmode' => env('PROD_SYNC_DB_SSLMODE', 'prefer'),
            ];
        }

        if (! is_string($driver) || trim($driver) === '') {
            return null;
        }

        return match ($driver) {
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => env('PROD_SYNC_DB_HOST', '127.0.0.1'),
                'port' => env('PROD_SYNC_DB_PORT', '5432'),
                'database' => env('PROD_SYNC_DB_DATABASE', ''),
                'username' => env('PROD_SYNC_DB_USERNAME', ''),
                'password' => env('PROD_SYNC_DB_PASSWORD', ''),
                'charset' => env('PROD_SYNC_DB_CHARSET', 'utf8'),
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => env('PROD_SYNC_DB_SCHEMA', 'public'),
                'sslmode' => env('PROD_SYNC_DB_SSLMODE', 'prefer'),
            ],
            'mysql', 'mariadb' => [
                'driver' => $driver,
                'host' => env('PROD_SYNC_DB_HOST', '127.0.0.1'),
                'port' => env('PROD_SYNC_DB_PORT', '3306'),
                'database' => env('PROD_SYNC_DB_DATABASE', ''),
                'username' => env('PROD_SYNC_DB_USERNAME', ''),
                'password' => env('PROD_SYNC_DB_PASSWORD', ''),
                'charset' => env('PROD_SYNC_DB_CHARSET', 'utf8mb4'),
                'collation' => env('PROD_SYNC_DB_COLLATION', 'utf8mb4_unicode_ci'),
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
            ],
            default => null,
        };
    }

    private function buildMunicipalitySyncKey(string $name, string $district, string $region): string
    {
        return mb_strtolower(trim($name)).'|'.mb_strtolower(trim($district)).'|'.mb_strtolower(trim($region));
    }

    private function resolveLocalSnapshotLandlordId(int $remoteLandlordId): int
    {
        $landlord = Landlord::query()->firstOrCreate(
            ['phone_number' => "prod-sync-landlord-{$remoteLandlordId}"],
            [
                'public_key' => null,
                'is_verified' => true,
            ],
        );

        return $landlord->id;
    }

    private function resolveLocalSnapshotEscortId(string $phoneNumber, ?int $externalId): int
    {
        $escort = Escort::query()->firstOrCreate(
            ['phone_number' => $phoneNumber],
            [
                'external_id' => $externalId,
                'public_key' => null,
            ],
        );

        if ($escort->external_id === null && $externalId !== null) {
            $escort->forceFill(['external_id' => $externalId])->save();
        }

        return $escort->id;
    }
}
