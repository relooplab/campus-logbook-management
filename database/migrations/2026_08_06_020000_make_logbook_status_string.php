<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubah kolom status logbook_entries dari enum menjadi string agar bisa
     * menampung status baru: revision_in_progress (Revisi sedang dikerjakan).
     */
    public function up(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->string('status', 50)->default('draft')->change();
        });
    }

    public function down(): void
    {
        // Kembalikan ke enum lama (nilai revision_in_progress diubah ke revisi).
        DB::table('logbook_entries')
            ->where('status', 'revision_in_progress')
            ->update(['status' => 'revisi']);

        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'approved', 'revisi'])->default('draft')->change();
        });
    }
};