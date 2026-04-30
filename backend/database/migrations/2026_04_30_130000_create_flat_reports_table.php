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
        Schema::create('flat_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_landlord_id')->nullable()->constrained('landlords')->cascadeOnDelete();
            $table->foreignId('reporter_escort_id')->nullable()->constrained('escorts')->cascadeOnDelete();
            $table->foreignId('reported_landlord_id')->nullable()->constrained('landlords')->cascadeOnDelete();
            $table->foreignId('reported_escort_id')->nullable()->constrained('escorts')->cascadeOnDelete();
            $table->string('reason_code', 64);
            $table->timestamps();

            $table->index(['flat_id', 'reporter_landlord_id']);
            $table->index(['flat_id', 'reporter_escort_id']);
            $table->unique(['flat_id', 'reporter_landlord_id', 'reported_escort_id'], 'flat_reports_landlord_target_unique');
            $table->unique(['flat_id', 'reporter_escort_id', 'reported_landlord_id'], 'flat_reports_escort_target_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flat_reports');
    }
};
