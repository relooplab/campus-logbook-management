<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konfigurasi penamaan program (TA/KP) & label fase per prodi/departemen.
     * Prodi dan departemen bisa punya penamaan berbeda.
     */
    public function up(): void
    {
        Schema::create('program_naming_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->enum('scope_type', ['study_program', 'department']);
            $table->unsignedBigInteger('scope_id');
            $table->enum('jenis', ['ta', 'kp']);
            $table->string('program_label', 100)->nullable();
            $table->json('fase_labels')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'scope_type', 'scope_id', 'jenis'], 'prog_naming_unique');
        });

        // Update fase TA: tambah penyusunan_proposal di awal, ganti analisis -> penyusunan_laporan.
        DB::table('mahasiswa_ta')
            ->where('jenis', 'ta')
            ->where('fase', 'analisis')
            ->update(['fase' => 'penyusunan_laporan']);
    }

    public function down(): void
    {
        // Kembalikan fase penyusunan_laporan -> analisis.
        DB::table('mahasiswa_ta')
            ->where('jenis', 'ta')
            ->where('fase', 'penyusunan_laporan')
            ->update(['fase' => 'analisis']);

        Schema::dropIfExists('program_naming_configs');
    }
};