<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan default untuk form pemberian bahan seminar/sidang.
 * Bisa diubah admin di pengaturan institusi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->text('seminar_hardcopy_note')->nullable()->after('allowed_file_types');
        });

        // Isi nilai default untuk catatan hardcopy.
        \Illuminate\Support\Facades\DB::table('institutions')->update([
            'seminar_hardcopy_note' => 'Jika diadakan secara offline, anda juga diminta menyerahkan hardcopy laporan paling lambat 48 jam sebelum jadwal Seminar/Sidang. Anda bisa menyerahkannya ke TU atau langsung ke ruangan saya.',
        ]);
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('seminar_hardcopy_note');
        });
    }
};