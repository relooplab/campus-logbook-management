<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan yang bisa diisi admin:
     * - template_url: link template catatan perbaikan (entri revisi)
     * - max_upload_size_mb: batas ukuran file upload (MB)
     * - allowed_file_types: jenis file yang diizinkan (comma-separated, mis. "pdf,doc,docx")
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('template_url')->nullable()->after('footer_note');
            $table->unsignedInteger('max_upload_size_mb')->default(10)->after('template_url');
            $table->string('allowed_file_types', 255)->default('pdf')->after('max_upload_size_mb');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['template_url', 'max_upload_size_mb', 'allowed_file_types']);
        });
    }
};