<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah foto kegiatan (maks 2, opsional) pada logbook harian KP.
     * Foto disimpan di disk 'public' agar dapat diakses via /storage.
     */
    public function up(): void
    {
        Schema::table('logbook_harian_kp', function (Blueprint $table) {
            $table->string('foto_1')->nullable()->after('kendala');
            $table->string('foto_2')->nullable()->after('foto_1');
        });
    }

    public function down(): void
    {
        Schema::table('logbook_harian_kp', function (Blueprint $table) {
            $table->dropColumn(['foto_1', 'foto_2']);
        });
    }
};