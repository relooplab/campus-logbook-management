<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pool kuota institusi di-input langsung (storage_limit_mb), bukan via plan.
 *
 * Model baru: institution subscribe = node + storage_limit_mb (pool langsung).
 * Plan hanya untuk dosen (individu). plan_id dibuat nullable (backward-compat)
 * dan storage_limit_mb nullable (fallback ke plan bila ada, utk data lama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directory_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_limit_mb')->nullable()->after('plan_id');
            $table->unsignedBigInteger('plan_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('directory_subscriptions', function (Blueprint $table) {
            $table->dropColumn('storage_limit_mb');
            $table->unsignedBigInteger('plan_id')->nullable(false)->change();
        });
    }
};
