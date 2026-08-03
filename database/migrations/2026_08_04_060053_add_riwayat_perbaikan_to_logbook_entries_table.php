<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat perbaikan entri revisi dalam bentuk tabel terstruktur (JSON).
     * Format setiap baris: { halaman, komentar_dosen, perbaikan, status }
     * status: Sudah / Sebagian / Belum.
     */
    public function up(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->json('riwayat_perbaikan')->nullable()->after('progres_kendala');
        });
    }

    public function down(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->dropColumn('riwayat_perbaikan');
        });
    }
};