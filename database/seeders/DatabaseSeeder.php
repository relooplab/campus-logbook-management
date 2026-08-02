<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\Institution;
use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::findOrCreate('admin');
        $dosenRole = Role::findOrCreate('dosen');
        $mahasiswaRole = Role::findOrCreate('mahasiswa');

        // Profil institusi default (single-row).
        Institution::firstOrCreate(
            ['id' => 1],
            [
                'app_name' => 'Thesis Logbook Management',
                'institution_name' => 'Perguruan Tinggi',
                'faculty' => null,
                'study_program' => null,
                'address' => null,
                'city' => null,
                'phone' => null,
                'email' => 'no-reply@example.com',
                'website' => '',
                'footer_note' => 'Dokumen ini dihasilkan oleh Thesis Logbook Management.',
            ]
        );

        // Seed daftar achievement (badge) dari definisi model.
        foreach (Achievement::definitions() as $code => [$icon, $name, $desc]) {
            Achievement::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => $desc, 'icon' => $icon]
            );
        }

        // Admin + dosen dalam satu akun (multi-role), NIDN sebagai identifier.
        $adminDosen = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Ir. Admin Utama, M.T.',
                'password' => Hash::make('password'),
                'identifier' => '0001010101', // NIDN
            ]
        );
        $adminDosen->syncRoles([$adminRole, $dosenRole]);

        // Dosen pembimbing kedua (opsional).
        $dosen2 = User::firstOrCreate(
            ['email' => 'dosen2@example.com'],
            [
                'name' => 'Dr. Dosen Dua, S.T., M.T.',
                'password' => Hash::make('password'),
                'identifier' => '0002020202', // NIDN
            ]
        );
        $dosen2->syncRoles([$dosenRole]);

        // Mahasiswa, NIM sebagai identifier.
        $mahasiswa = User::firstOrCreate(
            ['email' => 'mahasiswa@example.com'],
            [
                'name' => 'Mahasiswa Contoh',
                'password' => Hash::make('password'),
                'identifier' => '200401001', // NIM
            ]
        );
        $mahasiswa->syncRoles([$mahasiswaRole]);

        // Data pokok TA mahasiswa.
        MahasiswaTa::firstOrCreate(
            ['user_id' => $mahasiswa->id],
            [
                'judul_ta' => 'Perancangan Sistem Pengolahan Air Limbah Domestik Terpusat di Kawasan Permukiman',
                'pembimbing_1_id' => $adminDosen->id,
                'pembimbing_2_id' => $dosen2->id,
                'target_sesi' => 7,
            ]
        );
    }
}
