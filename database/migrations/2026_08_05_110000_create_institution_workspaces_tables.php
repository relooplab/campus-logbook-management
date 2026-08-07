<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace institusi — berbagi file di level direktori
 * (universitas/fakultas/departemen/prodi), terikat langganan.
 *
 * - institution_workspaces: workspace per node direktori.
 * - institution_workspace_files: file di dalam workspace.
 * - institution_workspace_allowed_users: akses custom (dosen tertentu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->enum('scope_type', ['university', 'faculty', 'department', 'study_program']);
            $table->unsignedBigInteger('scope_id');
            $table->string('name');
            // hierarchical = semua dosen di node yang sama + turunannya (default)
            // custom = hanya dosen yang terdaftar di allowed_users
            $table->enum('access_mode', ['hierarchical', 'custom'])->default('hierarchical');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('institution_workspace_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_workspace_id')->constrained('institution_workspaces')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('description')->nullable();
            // Fingerprint hapus (soft delete) — siapa yang menghapus.
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('institution_workspace_allowed_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_workspace_id');
            $table->foreignId('user_id');
            $table->timestamps();

            // Nama constraint eksplisit lebih pendek (MySQL limit 64 char).
            $table->foreign('institution_workspace_id', 'iw_allowed_ws_fk')
                ->references('id')->on('institution_workspaces')->cascadeOnDelete();
            $table->foreign('user_id', 'iw_allowed_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['institution_workspace_id', 'user_id'], 'iw_allowed_ws_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_workspace_allowed_users');
        Schema::dropIfExists('institution_workspace_files');
        Schema::dropIfExists('institution_workspaces');
    }
};