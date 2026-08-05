<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_quota_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('threshold'); // 80 | 95
            $table->timestamp('notified_at');
            $table->timestamps();

            $table->unique(['user_id', 'threshold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_quota_notifications');
    }
};