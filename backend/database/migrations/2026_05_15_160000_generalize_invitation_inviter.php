<?php

use App\Models\Landlord;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->nullableMorphs('inviter');
        });

        DB::table('invitations')
            ->whereNotNull('landlord_id')
            ->update([
                'inviter_type' => Landlord::class,
                'inviter_id' => DB::raw('landlord_id'),
            ]);

        Schema::table('invitations', function (Blueprint $table): void {
            $table->foreignId('landlord_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('invitations')
            ->whereNull('landlord_id')
            ->delete();

        Schema::table('invitations', function (Blueprint $table): void {
            $table->foreignId('landlord_id')->nullable(false)->change();
            $table->dropMorphs('inviter');
        });
    }
};
