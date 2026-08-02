<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            // proposal | pengumpulan_data | analisis | draft_final | sidang
            $table->string('fase')->default('proposal')->after('target_sesi');
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->dropColumn('fase');
        });
    }
};
