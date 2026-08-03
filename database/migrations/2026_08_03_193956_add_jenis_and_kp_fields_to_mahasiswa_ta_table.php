<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabuh tabel mahasiswa_ta menjadi "Program Mahasiswa":
     * - tambah kolom jenis (ta/kp) agar satu mahasiswa bisa punya 1 KP + 1 TA
     * - tambah kolom spesifik KP (tempat, pembimbing lapangan, periode)
     * - enforce satu record per (user_id, jenis) via unique constraint
     */
    public function up(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->string('jenis')->default('ta')->after('user_id');
            $table->string('tempat_kp')->nullable()->after('judul_ta');
            $table->string('pembimbing_lapangan')->nullable()->after('pembimbing_2_id');
            $table->date('periode_mulai')->nullable()->after('pembimbing_lapangan');
            $table->date('periode_selesai')->nullable()->after('periode_mulai');
        });

        // Satu mahasiswa maksimal 1 record per jenis (1 KP + 1 TA).
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->unique(['user_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'jenis']);
        });

        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->dropColumn(['jenis', 'tempat_kp', 'pembimbing_lapangan', 'periode_mulai', 'periode_selesai']);
        });
    }
};