<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel profil institusi (single-row). Dipakai untuk kebutuhan resmi:
     * kop dokumen rekap, pengirim email, brand aplikasi, kontak.
     */
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->default('Campus Logbook Management');
            $table->string('institution_name')->default('Perguruan Tinggi');
            $table->string('faculty')->nullable();
            $table->string('study_program')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('footer_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};
