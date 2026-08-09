<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Root fix 500 pada submit revisi: kolom `progres_kendala` dibuat NOT NULL
 * pada migrasi awal (2026_08_01_080642), namun field "Pesan untuk Dosen"
 * di form revisi bersifat opsional (nullable di StoreRevisiRequest).
 * Saat dikosongkan, nilainya null → insert ke kolom NOT NULL → 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->text('progres_kendala')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('logbook_entries', function (Blueprint $table) {
            $table->text('progres_kendala')->nullable(false)->change();
        });
    }
};
