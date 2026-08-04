<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Tambah kolom `nidn` (identitas dosen) di tabel users.
 * - Tabel pivot `user_university` untuk mendukung multi-universitas:
 *   satu dosen (atau mahasiswa) bisa terhubung ke banyak perguruan tinggi.
 *   `is_primary` menandai universitas utama. `study_program_id` adalah leaf
 *   yang menurunkan fakultas/departemen/university lewat relasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nidn')->nullable()->unique()->after('identifier');
        });

        Schema::create('user_university', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('university_id')->constrained('universities')->cascadeOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'university_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_university');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nidn']);
            $table->dropColumn('nidn');
        });
    }
};