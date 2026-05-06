<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('flat_reports', function (Blueprint $table) {
            $table->dropUnique('flat_reports_landlord_target_unique');
            $table->unique(
                ['flat_id', 'reporter_landlord_id', 'reported_escort_id', 'reason_code'],
                'flat_reports_landlord_target_reason_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flat_reports', function (Blueprint $table) {
            $table->dropUnique('flat_reports_landlord_target_reason_unique');
            $table->unique(
                ['flat_id', 'reporter_landlord_id', 'reported_escort_id'],
                'flat_reports_landlord_target_unique'
            );
        });
    }
};
