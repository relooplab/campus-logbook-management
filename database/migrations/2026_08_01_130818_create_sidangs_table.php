<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat sidang/pengujian mahasiswa oleh dosen (untuk pelaporan BKD).
     * Di-input oleh admin (data resmi prodi).
     */
    public function up(): void
    {
        Schema::create('sidangs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_ta_id')->constrained('mahasiswa_ta')->cascadeOnDelete();
            $table->foreignId('penguji_id')->constrained('users')->cascadeOnDelete();
            $table->string('jenis');   // seminar_proposal, sidang_akhir
            $table->date('tanggal');
            $table->string('hasil')->nullable(); // lulus, lulus_revisi, mengulang
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidangs');
    }
};
