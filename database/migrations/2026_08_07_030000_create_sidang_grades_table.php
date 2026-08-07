<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nilai seminar/sidang per penilai (pembimbing1, pembimbing2, penguji).
 * Setiap dosen yang terlibat mengisi nilai sendiri; mahasiswa melihat hasilnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidang_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sidang_id')->constrained('sidangs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role'); // pembimbing | penguji
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['sidang_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidang_grades');
    }
};
