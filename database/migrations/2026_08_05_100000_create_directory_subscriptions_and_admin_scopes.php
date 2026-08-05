<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Langganan direktori (directory_subscriptions) + top-up storage individu
 * (user_storage_addons) + pembatasan cakupan admin (admin_scopes).
 *
 * Semua tabel baru (ADDITIVE) — tidak mengubah subscriptions/plans/user_plan_overrides.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Langganan plan ke node direktori (universitas/fakultas/departemen/prodi).
        // Satu node aktif hanya boleh ada 1 (validasi no-overlap saat assign).
        Schema::create('directory_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->enum('scope_type', ['study_program', 'department', 'faculty', 'university']);
            $table->unsignedBigInteger('scope_id');
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('status')->default('active'); // active | expired | cancelled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id', 'status']);
        });

        // Top-up storage individual (additive di atas base plan/direktori).
        Schema::create('user_storage_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('storage_mb');
            $table->string('status')->default('active'); // active | expired | cancelled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Pembatasan cakupan admin (prodi/departemen/fakultas).
        // Tidak ada baris = institusi penuh. Ada baris = dibatasi ke scope ini.
        Schema::create('admin_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->enum('scope_type', ['study_program', 'department', 'faculty']);
            $table->unsignedBigInteger('scope_id');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active'); // active | revoked
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_scopes');
        Schema::dropIfExists('user_storage_addons');
        Schema::dropIfExists('directory_subscriptions');
    }
};