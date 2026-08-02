<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom profil pengguna: foto, kontak, dan tautan akademik (khusus dosen).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable()->after('identifier');
            $table->string('whatsapp', 30)->nullable();
            $table->string('telegram', 60)->nullable();
            $table->string('linkedin', 255)->nullable();
            // Khusus dosen / akademisi.
            $table->string('google_scholar', 255)->nullable();
            $table->string('orcid', 40)->nullable();
            $table->string('sinta', 40)->nullable();
            $table->string('researchgate', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_photo_path',
                'whatsapp',
                'telegram',
                'linkedin',
                'google_scholar',
                'orcid',
                'sinta',
                'researchgate',
            ]);
        });
    }
};
