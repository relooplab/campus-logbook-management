<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil singkat perusahaan tempat KP, diisi oleh mahasiswa.
     */
    public function up(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->text('profil_perusahaan')->nullable()->after('tempat_kp');
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->dropColumn('profil_perusahaan');
        });
    }
};