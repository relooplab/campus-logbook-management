<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akar Bug 2: kolom `catatan_hardcopy` dibuat NOT NULL pada migrasi awal
 * 2026_08_04_080000, namun diisi belakangan oleh dosen di halaman detail.
 * Buat nullable agar insert tanpa catatan tidak 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seminar_submissions', function (Blueprint $table) {
            $table->text('catatan_hardcopy')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('seminar_submissions', function (Blueprint $table) {
            $table->text('catatan_hardcopy')->nullable(false)->change();
        });
    }
};
