<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Memastikan default deployment adalah "user TIDAK wajib verifikasi email".
 *
 * Pada kolom `institutions.email_verification_required`:
 *   - default migration: `false` (0).
 *   - baris yang sudah ada mungkin ter-set `true` (mis. admin pernah
 *     mengaktifkan saat testing, atau deployment lama). Migration ini
 *     memaksa semua baris menjadi `false` sebagai safety net.
 *
 * System admin tetap dapat mengaktifkan toggle kapan saja di panel
 * Pengaturan; migration ini HANYA berjalan sekali saat deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('institutions')->update(['email_verification_required' => 0]);
    }

    public function down(): void
    {
        // Tidak ada rollback — nilai sebelumnya tidak diketahui.
    }
};
