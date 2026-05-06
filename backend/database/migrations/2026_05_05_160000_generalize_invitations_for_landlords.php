<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->string('invited_role')->default('escort')->after('phone_number');
            $table->index('invited_role');
        });

        DB::table('invitations')->update([
            'invited_role' => 'escort',
        ]);

        Schema::table('landlords', function (Blueprint $table): void {
            $table->string('phone_number')->nullable()->after('id');
            $table->unique('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('landlords', function (Blueprint $table): void {
            $table->dropUnique(['phone_number']);
            $table->dropColumn('phone_number');
        });

        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropIndex(['invited_role']);
            $table->dropColumn('invited_role');
        });
    }
};
