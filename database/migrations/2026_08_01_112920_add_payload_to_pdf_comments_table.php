<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom `payload` untuk menyimpan anotasi lengkap
     * berformat W3C Web Annotation (JSON), terpisah dari file PDF.
     * Kolom geometri lama tetap dipertahankan untuk render cepat.
     */
    public function up(): void
    {
        Schema::table('pdf_comments', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('pdf_comments', function (Blueprint $table) {
            $table->dropColumn('payload');
        });
    }
};
