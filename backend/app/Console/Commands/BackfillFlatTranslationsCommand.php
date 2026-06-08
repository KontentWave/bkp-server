<?php

namespace App\Console\Commands;

use App\Models\Flat;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'translations:backfill-flat-cache',
    description: 'Seed pending translation jobs for existing flats',
)]
class BackfillFlatTranslationsCommand extends Command
{
    protected $signature = 'translations:backfill-flat-cache
        {--flat-id=* : Restrict backfill to specific flat IDs}
        {--chunk=200 : Number of flats to process per batch}';

    protected $description = 'Seed pending translation jobs for existing flats';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $flatIds = array_values(array_filter(array_map(
            static fn (mixed $value): int => (int) $value,
            (array) $this->option('flat-id'),
        )));

        $query = Flat::query()->orderBy('id');

        if ($flatIds !== []) {
            $query->whereIn('id', $flatIds);
        }

        $processedCount = 0;

        $query->eachById(function (Flat $flat) use (&$processedCount): void {
            $flat->syncTranslationJobs();
            $processedCount++;
        }, $chunkSize);

        $this->info('Seeded translation jobs for '.$processedCount.' flat(s).');

        return self::SUCCESS;
    }
}
