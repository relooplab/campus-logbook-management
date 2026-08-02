<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase D: sidang bisa mencatat mahasiswa ORANG LAIN (di luar bimbingan).
 * Re-build tabel agar:
 *  - mahasiswa_ta_id nullable (bila mahasiswa tidak punya TA di sistem).
 *  - tambah mahasiswa_name (manual) untuk mahasiswa di luar sistem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sidangs');

        Schema::create('sidangs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->nullOnDelete();
            $table->foreignId('mahasiswa_ta_id')->nullable()->constrained('mahasiswa_ta')->cascadeOnDelete();
            $table->string('mahasiswa_name')->nullable(); // utk mahasiswa di luar sistem
            $table->foreignId('penguji_id')->constrained('users')->cascadeOnDelete();
            $table->string('jenis');   // seminar_proposal, sidang_akhir
            $table->date('tanggal');
            $table->string('hasil')->nullable(); // lulus, lulus_revisi, mengulang
            $table->json('supervisor_names')->nullable(); // nama pembimbing (maks 3)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidangs');

        Schema::create('sidangs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_ta_id')->constrained('mahasiswa_ta')->cascadeOnDelete();
            $table->foreignId('penguji_id')->constrained('users')->cascadeOnDelete();
            $table->string('jenis');
            $table->date('tanggal');
            $table->string('hasil')->nullable();
            $table->timestamps();
        });
    }
};
