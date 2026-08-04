<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Set email_verified_at untuk user yang sudah ada (agar tidak terkunci
 * saat MustVerifyEmail diaktifkan). User baru harus verifikasi email.
 */
return new class extends Migration
{
    public function up(): void
    {
        // User yang sudah ada dianggap sudah verifikasi email.
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Tidak ada rollback yang aman — biarkan email_verified_at tetap terisi.
    }
};