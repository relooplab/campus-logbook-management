<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah alamat perusahaan & jenis instansi pada profil perusahaan KP.
     */
    public function up(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->string('alamat_perusahaan')->nullable()->after('tempat_kp');
            $table->string('jenis_instansi')->nullable()->after('alamat_perusahaan');
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->dropColumn(['alamat_perusahaan', 'jenis_instansi']);
        });
    }
};