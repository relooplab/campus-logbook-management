<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rename users.identifier -> users.nim.
 *
 * Fondasi identitas: NIM khusus mahasiswa (users.nim), NIDN khusus dosen
 * (users.nidn). Sebelumnya `identifier` dipakai ganda (NIM mahasiswa & NIDN
 * dosen via import/seeder). Rename memperjelas semantik & menyiapkan
 * validasi unik lintas kolom yang bersih.
 *
 * renameColumn mempertahankan nullable + unique index pada kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('identifier', 'nim');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('nim', 'identifier');
        });
    }
};
