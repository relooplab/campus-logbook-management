<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom ukuran (bytes) untuk lampiran & catatan perbaikan,
 * digunakan oleh perhitungan pemakaian penyimpanan (storage quota).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('lampiran_size')->nullable()->after('lampiran_path');
            $table->unsignedBigInteger('catatan_perbaikan_size')->nullable()->after('catatan_perbaikan_path');
        });
    }

    public function down(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->dropColumn(['lampiran_size', 'catatan_perbaikan_size']);
        });
    }
};