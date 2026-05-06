<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->unsignedBigInteger('escort_external_id')->nullable()->after('phone_number');
            $table->index('escort_external_id');
        });

        Schema::table('escorts', function (Blueprint $table): void {
            $table->unsignedBigInteger('external_id')->nullable()->after('id');
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropIndex(['escort_external_id']);
            $table->dropColumn('escort_external_id');
        });

        Schema::table('escorts', function (Blueprint $table): void {
            $table->dropIndex(['external_id']);
            $table->dropColumn('external_id');
        });
    }
};
