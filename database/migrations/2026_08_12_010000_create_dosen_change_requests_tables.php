<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan pengusulan/mengganti dosen penguji oleh mahasiswa,
 * disertai persetujuan dari SEMUA dosen yang terlibat (pembimbing &
 * penguji) + calon penguji baru.
 *
 * Penerapan ke `mahasiswa_ta.penguji_1_id/penguji_2_id` hanya dilakukan
 * setelah semua approver menyetujui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dosen_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_ta_id')->constrained('mahasiswa_ta')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('proposed_role'); // penguji_1 | penguji_2
            $table->foreignId('proposed_dosen_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->string('alasan_tolak')->nullable();
            $table->timestamps();

            $table->index(['mahasiswa_ta_id', 'status']);
        });

        Schema::create('dosen_change_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('dosen_change_requests')->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained('users')->cascadeOnDelete();
            $table->string('status'); // approved | rejected
            $table->string('note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['request_id', 'dosen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dosen_change_approvals');
        Schema::dropIfExists('dosen_change_requests');
    }
};