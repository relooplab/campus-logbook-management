<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Form pemberian bahan seminar/sidang oleh mahasiswa.
 * Data jadwal + file undangan + materi (upload atau dari workspace).
 * Bisa dikonversi dosen menjadi riwayat sidang (tabel sidangs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seminar_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_ta_id')->constrained('mahasiswa_ta')->cascadeOnDelete();
            $table->string('jenis'); // seminar_proposal, seminar_hasil, sidang_akhir, seminar_kp
            $table->date('tanggal');
            $table->time('waktu');
            $table->string('lokasi')->nullable();
            $table->string('undangan_path');
            $table->string('undangan_original_name');
            $table->string('undangan_sebagai'); // pembimbing_1, pembimbing_2, penguji_1, penguji_2
            $table->string('materi_path')->nullable();
            $table->string('materi_original_name')->nullable();
            $table->foreignId('materi_workspace_file_id')->nullable()->constrained('workspace_files')->nullOnDelete();
            $table->text('catatan_hardcopy');
            $table->text('catatan_keterangan')->nullable();
            $table->string('status')->default('draft'); // draft, submitted
            $table->foreignId('sidang_id')->nullable()->constrained('sidangs')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seminar_submissions');
    }
};