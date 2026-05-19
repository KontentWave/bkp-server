<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('district');
            $table->string('region');

            $table->index('name');
            $table->unique(['name', 'district', 'region']);
        });

        $rows = $this->parseMunicipalityRows();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('municipalities')->insert($chunk);
        }

        Schema::table('flats', function (Blueprint $table): void {
            $table->foreignId('municipality_id')
                ->nullable()
                ->after('viber_url')
                ->constrained('municipalities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('flats', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('municipality_id');
        });

        Schema::dropIfExists('municipalities');
    }

    /**
     * @return array<int, array{name: string, district: string, region: string}>
     */
    private function parseMunicipalityRows(): array
    {
        $filePath = dirname(base_path()).'/.github/docs/obce-kraje.md';

        if (! is_file($filePath)) {
            throw new \RuntimeException('Municipality source file not found: '.$filePath);
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new \RuntimeException('Municipality source file could not be read.');
        }

        $rows = [];
        $lastName = null;
        $lastRegion = null;

        foreach ($lines as $index => $line) {
            if ($index === 0) {
                continue;
            }

            $columns = array_pad(explode("\t", $line), 3, '');
            $name = trim($columns[0]);
            $district = trim($columns[1]);
            $region = trim($columns[2]);

            if ($name === '' && $lastName !== null) {
                $name = $lastName;
            }

            if ($region === '' && $lastRegion !== null) {
                $region = $lastRegion;
            }

            if ($name === '' || $district === '' || $region === '') {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'district' => $district,
                'region' => $region,
            ];

            $lastName = $name;
            $lastRegion = $region;
        }

        return $rows;
    }
};
