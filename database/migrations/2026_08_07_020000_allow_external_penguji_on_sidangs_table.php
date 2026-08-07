<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Izinkan penguji eksternal (dosen di luar sistem) pada riwayat sidang:
 * - tambah kolom `penguji_name` (nama manual).
 * - buat `penguji_id` nullable + nullOnDelete (penguji bisa tidak terdaftar di sistem).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sidangs', function (Blueprint $table) {
            $table->string('penguji_name')->nullable()->after('penguji_id');
        });

        Schema::table('sidangs', function (Blueprint $table) {
            $table->dropForeign(['penguji_id']);
            $table->foreignId('penguji_id')->nullable()->change();
            $table->foreign('penguji_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sidangs', function (Blueprint $table) {
            $table->dropForeign(['penguji_id']);
            $table->foreignId('penguji_id')->nullable(false)->change();
            $table->foreign('penguji_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('sidangs', function (Blueprint $table) {
            $table->dropColumn('penguji_name');
        });
    }
};
