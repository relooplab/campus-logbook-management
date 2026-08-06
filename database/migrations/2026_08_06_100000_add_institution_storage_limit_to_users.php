<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batas storage per-user dalam shared pool institusi (MB).
 * NULL = unlimited dalam pool (mengikuti batas pool).
 * Hanya berlaku untuk user institusi (institution_id terisi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('institution_storage_limit_mb')->nullable()->after('institution_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('institution_storage_limit_mb');
        });
    }
};