<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan email (SMTP) yang bisa diisi admin.
     * Nilai ini diterapkan dinamis via Institution::applyToConfig().
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('mail_mailer', 20)->default('smtp')->after('allowed_file_types');
            $table->string('mail_host', 255)->nullable()->after('mail_mailer');
            $table->unsignedInteger('mail_port')->nullable()->after('mail_host');
            $table->string('mail_username', 255)->nullable()->after('mail_port');
            $table->string('mail_password', 255)->nullable()->after('mail_username');
            $table->string('mail_encryption', 20)->nullable()->after('mail_password');
            $table->string('mail_from_address', 255)->nullable()->after('mail_encryption');
            $table->string('mail_from_name', 255)->nullable()->after('mail_from_address');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn([
                'mail_mailer',
                'mail_host',
                'mail_port',
                'mail_username',
                'mail_password',
                'mail_encryption',
                'mail_from_address',
                'mail_from_name',
            ]);
        });
    }
};