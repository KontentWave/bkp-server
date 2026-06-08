<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flat_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flat_id')->constrained()->cascadeOnDelete();
            $table->string('field_name', 32);
            $table->string('language', 8);
            $table->string('source_hash', 64);
            $table->text('source_text');
            $table->text('translated_text')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('provider', 120)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->timestamp('translated_at')->nullable();
            $table->timestamps();

            $table->unique(['flat_id', 'field_name', 'language']);
            $table->index(['status', 'updated_at']);
            $table->index(['language', 'field_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flat_translations');
    }
};
