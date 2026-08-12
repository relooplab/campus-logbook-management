<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom override verifikasi email (null = Auto, true = Wajib, false = Tidak).
     * Backfill dari kolom lama `email_verification_required`.
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->boolean('email_verification_override')->nullable()->after('email_verification_required');
        });

        DB::table('institutions')
            ->where('email_verification_required', true)
            ->update(['email_verification_override' => true]);
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('email_verification_override');
        });
    }
};