<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tanggal pengiriman revisi — pengganti Tanggal Bimbingan untuk entri
     * revisi, agar tanggal tidak kosong (revisi tidak punya tanggal bimbingan).
     */
    public function up(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->date('tanggal_pengiriman')->nullable()->after('tanggal_bimbingan');
        });
    }

    public function down(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->dropColumn('tanggal_pengiriman');
        });
    }
};
