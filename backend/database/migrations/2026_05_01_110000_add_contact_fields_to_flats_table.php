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
        Schema::table('flats', function (Blueprint $table) {
            $table->string('contact_phone', 32)->nullable()->after('description');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->string('whatsapp_url')->nullable()->after('contact_email');
            $table->string('telegram_url')->nullable()->after('whatsapp_url');
            $table->string('viber_url')->nullable()->after('telegram_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flats', function (Blueprint $table) {
            $table->dropColumn([
                'contact_phone',
                'contact_email',
                'whatsapp_url',
                'telegram_url',
                'viber_url',
            ]);
        });
    }
};
