<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Toggle notifikasi berkala per institusi (default ON = perilaku lama).
     * - weekly_digest_enabled: digest mingguan Senin 07:00 (ta:weekly-digest).
     * - daily_reminder_enabled: reminder harian 08:00 (logbook:send-reminders
     *   + ta:notify-inactive).
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->boolean('weekly_digest_enabled')->default(true)->after('admin_contact_email');
            $table->boolean('daily_reminder_enabled')->default(true)->after('weekly_digest_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['weekly_digest_enabled', 'daily_reminder_enabled']);
        });
    }
};
