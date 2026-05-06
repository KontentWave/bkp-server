<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flat_reports', function (Blueprint $table): void {
            $table->unsignedBigInteger('reported_escort_external_id')->nullable()->after('reported_escort_id');
            $table->index('reported_escort_external_id');
            $table->unique(
                ['flat_id', 'reporter_landlord_id', 'reported_escort_external_id', 'reason_code'],
                'flat_reports_landlord_external_target_reason_unique'
            );
        });

        DB::statement('UPDATE flat_reports SET reported_escort_external_id = escorts.external_id FROM escorts WHERE flat_reports.reported_escort_id = escorts.id AND escorts.external_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('flat_reports', function (Blueprint $table): void {
            $table->dropUnique('flat_reports_landlord_external_target_reason_unique');
            $table->dropIndex(['reported_escort_external_id']);
            $table->dropColumn('reported_escort_external_id');
        });
    }
};
