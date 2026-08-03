<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Logbook harian KP: catatan kegiatan lapangan singkat mahasiswa selama
     * periode KP. Tidak ada alur review/approval — hanya catatan harian.
     */
    public function up(): void
    {
        Schema::create('logbook_harian_kp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_ta_id')->constrained('mahasiswa_ta')->cascadeOnDelete();
            $table->date('tanggal');
            $table->text('kegiatan');
            $table->text('kendala')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_harian_kp');
    }
};