<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom opt-in jalur kontak bimbingan (khusus dosen) — diisi di profil dosen,
     * digunakan untuk menampilkan jalur kontak yang tersedia di halaman Jadwal Bimbingan.
     * Jika dosen belum mengisi link jadwal bimbingan eksternal, mahasiswa bisa
     * menghubungi via WhatsApp atau Telegram sesuai centangan yang diaktifkan.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('bimbingan_via_whatsapp')->default(false)->after('jadwal_bimbingan_url');
            $table->boolean('bimbingan_via_telegram')->default(false)->after('bimbingan_via_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bimbingan_via_whatsapp', 'bimbingan_via_telegram']);
        });
    }
};