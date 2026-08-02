<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom untuk mode aplikasi (individual/institusi) + status registrasi mahasiswa.
 * - institution_id = NULL berarti milik pemilik data (mode individual).
 * - registration_status: pending/approved/rejected untuk registrasi mahasiswa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('id')->constrained('institutions')->nullOnDelete();
            $table->string('registration_status')->default('approved')->after('identifier');
            $table->json('examiner_supervisor_names')->nullable()->after('registration_status');
        });

        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('id')->constrained('institutions')->nullOnDelete();
        });

        Schema::table('sidangs', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('id')->constrained('institutions')->nullOnDelete();
            // Konteks pembimbing mahasiswa yang diuji (maks 3, untuk penguji mahasiswa lain).
            $table->string('supervisor_names')->nullable()->after('hasil');
        });
    }

    public function down(): void
    {
        Schema::table('sidangs', function (Blueprint $table) {
            $table->dropForeign(['institution_id']);
            $table->dropColumn(['institution_id', 'supervisor_names']);
        });

        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->dropForeign(['institution_id']);
            $table->dropColumn('institution_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['institution_id']);
            $table->dropColumn(['institution_id', 'registration_status', 'examiner_supervisor_names']);
        });
    }
};
