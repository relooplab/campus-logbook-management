<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status mahasiswa baru:
 * - active   : sudah verifikasi email, belum attach dosen
 * - verified : sudah punya MahasiswaTa dengan dosen (disetujui)
 *
 * Data yang sudah ada:
 * - Mahasiswa dengan MahasiswaTa yang punya dosen (pembimbing/penguji) → verified
 * - Mahasiswa lain (pending) → active (sudah verifikasi email via migration sebelumnya)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Mahasiswa yang sudah punya MahasiswaTa dengan dosen → verified.
        DB::table('users')
            ->join('mahasiswa_ta', 'mahasiswa_ta.user_id', '=', 'users.id')
            ->where('users.registration_status', '!=', 'rejected')
            ->where(function ($q) {
                $q->whereNotNull('mahasiswa_ta.pembimbing_1_id')
                    ->orWhereNotNull('mahasiswa_ta.pembimbing_2_id')
                    ->orWhereNotNull('mahasiswa_ta.penguji_1_id')
                    ->orWhereNotNull('mahasiswa_ta.penguji_2_id');
            })
            ->update(['users.registration_status' => 'verified']);

        // Mahasiswa yang tidak punya MahasiswaTa dengan dosen → active.
        DB::table('users')
            ->where('registration_status', 'pending')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('mahasiswa_ta')
                    ->whereColumn('mahasiswa_ta.user_id', 'users.id');
            })
            ->update(['registration_status' => 'active']);
    }

    public function down(): void
    {
        // Tidak ada rollback yang aman.
    }
};