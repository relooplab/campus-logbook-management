<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Toggle verifikasi email: jika ON, user yang baru registrasi HARUS
     * verifikasi email sebelum bisa masuk ke fitur aplikasi. Setting ini
     * diurus system admin di panel Pengaturan Autentikasi.
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->boolean('email_verification_required')->default(false)->after('allowed_file_types');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('email_verification_required');
        });
    }
};
