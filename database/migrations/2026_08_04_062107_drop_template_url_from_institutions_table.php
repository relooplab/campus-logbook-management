<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom template_url tidak lagi dipakai sejak catatan perbaikan dibuat
     * otomatis dari tabel (bukan template PDF eksternal).
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('template_url');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('template_url')->nullable()->after('footer_note');
        });
    }
};