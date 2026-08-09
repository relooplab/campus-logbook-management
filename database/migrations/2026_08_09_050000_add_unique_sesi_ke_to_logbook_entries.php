<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cegah race condition sesi_ke (TOCTOU) pada entri logbook.
 *
 * Pendekatan PORTABEL (MySQL, PostgreSQL, SQLite):
 * - sesi_ke dibuat nullable; entri revisi memakai NULL (bukan 0).
 * - Unique index (mahasiswa_ta_id, sesi_ke) — NULL boleh banyak di semua DB,
 *   jadi revisi (NULL) tidak bentrok, dan sesi logbook dijamin unik per TA.
 *
 * (Bukan partial index WHERE jenis='logbook' — itu PostgreSQL-only.)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Renumber duplikat sesi_ke logbook yang sudah terlanjur ada.
        $taIds = DB::table('logbook_entries')
            ->select('mahasiswa_ta_id')
            ->where('jenis', \App\Models\LogbookEntry::JENIS_LOGBOOK)
            ->groupBy('mahasiswa_ta_id', 'sesi_ke')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('mahasiswa_ta_id')
            ->unique()
            ->values();

        foreach ($taIds as $taId) {
            $rows = DB::table('logbook_entries')
                ->where('mahasiswa_ta_id', $taId)
                ->where('jenis', \App\Models\LogbookEntry::JENIS_LOGBOOK)
                ->orderBy('id')
                ->get();

            $n = 1;
            foreach ($rows as $row) {
                DB::table('logbook_entries')->where('id', $row->id)->update(['sesi_ke' => $n++]);
            }
        }

        // 2) sesi_ke jadi nullable.
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->unsignedInteger('sesi_ke')->nullable()->change();
        });

        // 3) Revisi lama (sesi_ke=0) -> NULL, agar tidak bentrok di unique index.
        DB::statement(
            "UPDATE logbook_entries SET sesi_ke = NULL WHERE jenis = '".\App\Models\LogbookEntry::JENIS_REVISI."'"
        );

        // 4) Unique index biasa — NULL boleh banyak (kompatibel semua DB).
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->unique(['mahasiswa_ta_id', 'sesi_ke']);
        });
    }

    public function down(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->dropUnique(['logbook_entries_mahasiswa_ta_id_sesi_ke_unique']);
            $table->unsignedInteger('sesi_ke')->nullable(false)->change();
        });
    }
};
