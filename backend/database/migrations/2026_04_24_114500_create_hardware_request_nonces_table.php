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
        Schema::create('hardware_request_nonces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_access_token_id')->constrained()->cascadeOnDelete();
            $table->string('nonce_hash', 64);
            $table->timestamp('used_at');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['personal_access_token_id', 'nonce_hash'], 'hardware_nonces_token_nonce_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hardware_request_nonces');
    }
};
