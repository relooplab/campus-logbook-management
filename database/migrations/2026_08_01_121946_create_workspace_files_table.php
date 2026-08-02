<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Workspace = mini Google Drive per mahasiswa.
     * File kerja bebas (PDF/DOC/DOCX/XLS/XLSX, max 25MB), dua arah:
     * mahasiswa & dosen pembimbing bisa upload/lihat/download.
     */
    public function up(): void
    {
        Schema::create('workspace_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_ta_id')->constrained('mahasiswa_ta')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('bab')->nullable();       // label bebas, tidak dikunci struktur bab
            $table->string('original_name');          // nama asli utk download
            $table->string('path');                   // storage path
            $table->string('mime_type');
            $table->unsignedBigInteger('size');       // bytes
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_files');
    }
};
