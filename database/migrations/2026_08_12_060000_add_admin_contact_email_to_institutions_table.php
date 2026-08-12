<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email kontak admin default (diisi system admin) yang bisa di-override per
 * institusi. Dipakai sebagai info bantuan di halaman register/login/profil,
 * mis. untuk koreksi NIDN yang salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->string('admin_contact_email', 255)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('admin_contact_email');
        });
    }
};
