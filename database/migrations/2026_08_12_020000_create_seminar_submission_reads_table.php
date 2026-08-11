<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pelacakan "sudah dibaca" sebuah submission seminar/sidang per dosen.
 * Dipakai untuk badge "Baru / Belum dibaca" pada halaman Agenda Seminar/Sidang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seminar_submission_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seminar_submission_id')->constrained('seminar_submissions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['seminar_submission_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seminar_submission_reads');
    }
};