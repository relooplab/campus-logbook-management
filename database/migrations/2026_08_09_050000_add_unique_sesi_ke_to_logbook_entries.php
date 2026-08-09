<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cegah race condition sesi_ke (TOCTOU) pada logbook: tambah unique index
 * (mahasiswa_ta_id, sesi_ke). Sebelum membuat index, renumber duplikat yang
 * sudah terlanjur ada (pertahankan urutan id) agar index bisa dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Renumber sesi_ke per mahasiswa yang punya duplikat (1..N urut by id).
        $taIds = \Illuminate\Support\Facades\DB::table('logbook_entries')
            ->select('mahasiswa_ta_id')
            ->where('jenis', \App\Models\LogbookEntry::JENIS_LOGBOOK)
            ->groupBy('mahasiswa_ta_id', 'sesi_ke')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('mahasiswa_ta_id')
            ->unique()
            ->values();

        foreach ($taIds as $taId) {
            $rows = \Illuminate\Support\Facades\DB::table('logbook_entries')
                ->where('mahasiswa_ta_id', $taId)
                ->where('jenis', \App\Models\LogbookEntry::JENIS_LOGBOOK)
                ->orderBy('id')
                ->get();

            $n = 1;
            foreach ($rows as $row) {
                \Illuminate\Support\Facades\DB::table('logbook_entries')
                    ->where('id', $row->id)
                    ->update(['sesi_ke' => $n++]);
            }
        }

        // Partial unique index: hanya untuk entri logbook. Revisi memakai
        // sesi_ke=0 berulang per TA, sehingga tidak boleh di-unique penuh.
        \Illuminate\Support\Facades\DB::statement(
            "CREATE UNIQUE INDEX logbook_entries_ta_sesi_unique ON logbook_entries(mahasiswa_ta_id, sesi_ke) WHERE jenis = 'logbook'"
        );
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement('DROP INDEX logbook_entries_ta_sesi_unique');
    }
};
