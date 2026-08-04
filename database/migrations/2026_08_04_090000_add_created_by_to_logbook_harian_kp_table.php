<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akuntabilitas KP kelompok: catat siapa penulis asli catatan harian.
 * Hanya penulis (created_by) yang boleh edit/hapus catatannya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logbook_harian_kp', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('mahasiswa_ta_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('logbook_harian_kp', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};