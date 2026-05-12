<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->string('escort_ad_url')->nullable()->after('escort_external_id');
            $table->timestamp('phone_scraped_at')->nullable()->after('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropColumn(['escort_ad_url', 'phone_scraped_at']);
        });
    }
};
