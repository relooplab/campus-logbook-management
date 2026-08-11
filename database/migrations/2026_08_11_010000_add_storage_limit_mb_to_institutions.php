<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kuota storage langsung per institusi (override pool).
 *
 *   NULL (default) = mengikuti hitungan subscription direktori (backward-compatible).
 *   terisi (MB)    = override pool institusi secara langsung (menggantikan subscription).
 *
 * Dipakai di Feature::institutionStorageLimitMb() sebagai prioritas pertama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_limit_mb')->nullable()->after('email_verification_required');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('storage_limit_mb');
        });
    }
};
