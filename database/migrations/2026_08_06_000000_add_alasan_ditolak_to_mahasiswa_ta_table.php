<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom alasan penolakan permintaan attachment dosen — diisi wajib saat
     * dosen menolak, ditampilkan ke mahasiswa agar tahu alasan penolakan.
     */
    public function up(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->string('alasan_ditolak', 255)->nullable()->after('status_ta');
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->dropColumn('alasan_ditolak');
        });
    }
};