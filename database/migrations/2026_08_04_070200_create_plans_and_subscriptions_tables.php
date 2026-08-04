<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Struktur paket (Free vs Donasi) + langganan + override admin per-user.
 *
 * - plans: definisi paket (free/donasi) dengan fitur JSON.
 * - subscriptions: langganan user ke paket.
 * - user_plan_overrides: admin bisa override fitur per individu (custom).
 * - workspace_files.user_id: mendukung workspace milik dosen (nullable;
 *   mahasiswa tetap memakai mahasiswa_ta_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // free | donasi
            $table->string('label')->nullable(); // "Gratis" | "Donasi"
            $table->decimal('price', 12, 2)->default(0);
            $table->string('period')->default('monthly'); // monthly | yearly | one_time
            $table->json('features')->nullable(); // {export, import, storage_mb}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('status')->default('active'); // active | expired | cancelled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_plan_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('allow_export')->nullable(); // null = ikut plan
            $table->boolean('allow_import')->nullable(); // null = ikut plan
            $table->unsignedBigInteger('storage_limit_mb')->nullable(); // null = ikut plan
            $table->timestamps();

            $table->unique('user_id');
        });

        // Workspace dosen: file milik user (tanpa mahasiswa_ta_id).
        Schema::table('workspace_files', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workspace_files', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::dropIfExists('user_plan_overrides');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};