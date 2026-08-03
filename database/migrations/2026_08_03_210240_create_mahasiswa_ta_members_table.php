<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anggota kelompok program mahasiswa (khusus KP).
     * Pemilik utama tetap di kolom user_id pada tabel mahasiswa_ta;
     * anggota tambahan dicatat di sini (pivot).
     */
    public function up(): void
    {
        Schema::create('mahasiswa_ta_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_ta_id')->constrained('mahasiswa_ta')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['mahasiswa_ta_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahasiswa_ta_members');
    }
};