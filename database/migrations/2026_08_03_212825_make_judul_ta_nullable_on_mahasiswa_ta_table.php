<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Judul TA hanya wajib untuk program TA; untuk KP boleh kosong.
     */
    public function up(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->string('judul_ta')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa_ta', function (Blueprint $table) {
            $table->string('judul_ta')->nullable(false)->change();
        });
    }
};