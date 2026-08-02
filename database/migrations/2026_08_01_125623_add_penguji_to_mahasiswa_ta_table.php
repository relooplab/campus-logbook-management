<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah peran penguji 1 & 2 pada data TA (selain pembimbing).
     */
    public function up(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->foreignId('penguji_1_id')->nullable()->after('pembimbing_2_id')->constrained('users')->nullOnDelete();
            $table->foreignId('penguji_2_id')->nullable()->after('penguji_1_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->dropForeign(['penguji_1_id']);
            $table->dropForeign(['penguji_2_id']);
            $table->dropColumn(['penguji_1_id', 'penguji_2_id']);
        });
    }
};
