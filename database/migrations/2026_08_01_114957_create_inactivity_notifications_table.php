<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inactivity_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_ta_id')->constrained('mahasiswa_ta')->cascadeOnDelete();
            $table->timestamp('notified_at');
            $table->integer('inactive_days');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inactivity_notifications');
    }
};
