<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simpan nama asli file (untuk download dengan nama asli).
     * File disimpan di path unik {entry_id}/{uuid}.pdf.
     */
    public function up(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->string('lampiran_original_name')->nullable()->after('lampiran_path');
            $table->string('catatan_original_name')->nullable()->after('catatan_perbaikan_path');
        });
    }

    public function down(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->dropColumn(['lampiran_original_name', 'catatan_original_name']);
        });
    }
};
