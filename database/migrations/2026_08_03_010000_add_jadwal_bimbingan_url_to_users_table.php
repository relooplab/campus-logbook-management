<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom link jadwal bimbingan (khusus dosen) — diisi di profil dosen,
     * ditampilkan sebagai card hyperlink di halaman Jadwal Bimbingan.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('jadwal_bimbingan_url', 255)->nullable()->after('researchgate');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('jadwal_bimbingan_url');
        });
    }
};