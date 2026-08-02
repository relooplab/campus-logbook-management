<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Target bimbingan minimal 7 sesi (bukan maksimal 16).
     * Mengubah default kolom target_sesi.
     */
    public function up(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->unsignedInteger('target_sesi')->default(7)->change();
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->unsignedInteger('target_sesi')->default(16)->change();
        });
    }
};
