<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_subscription_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('directory_subscription_id');
            $table->string('notif_type'); // 'h7' | 'h1' | 'expired'
            $table->timestamp('notified_at');
            $table->timestamps();

            // Nama constraint eksplisit lebih pendek (MySQL limit 64 char).
            $table->foreign('directory_subscription_id', 'dsn_subscription_fk')
                ->references('id')->on('directory_subscriptions')->cascadeOnDelete();
            $table->unique(['directory_subscription_id', 'notif_type'], 'dsn_sub_notif_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_subscription_notifications');
    }
};
