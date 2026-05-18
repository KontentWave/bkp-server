<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flat_reports', function (Blueprint $table): void {
            $table->dropUnique('flat_reports_escort_target_unique');
            $table->unique(
                ['flat_id', 'reporter_escort_id', 'reported_landlord_id', 'reason_code'],
                'flat_reports_escort_target_reason_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('flat_reports', function (Blueprint $table): void {
            $table->dropUnique('flat_reports_escort_target_reason_unique');
            $table->unique(
                ['flat_id', 'reporter_escort_id', 'reported_landlord_id'],
                'flat_reports_escort_target_unique'
            );
        });
    }
};
