<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Institution;
use App\Models\LogbookHarianKp;
use App\Models\MahasiswaTa;
use App\Models\Plan;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $systemAdminRole = Role::findOrCreate('system_admin');
        $adminRole = Role::findOrCreate('admin');
        $dosenRole = Role::findOrCreate('dosen');
        $mahasiswaRole = Role::findOrCreate('mahasiswa');

        // Sync permissions ke role (pastikan konsisten setiap seed).
        $this->syncPermissions();

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

        // Seed paket (Free vs Donasi).
        Plan::firstOrCreate(
            ['name' => 'free'],
            [
                'label' => 'Gratis',
                'price' => 0,
                'period' => 'monthly',
                'features' => [
                    'export' => false,
                    'import' => false,
                    'storage_mb' => 5120, // 5 GB
                ],
                'is_active' => true,
            ]
        );
        Plan::firstOrCreate(
            ['name' => 'donasi'],
            [
                'label' => 'Donasi',
                'price' => 50000,
                'period' => 'monthly',
                'features' => [
                    'export' => true,
                    'import' => true,
                    'storage_mb' => 10240, // 10 GB
                ],
                'is_active' => true,
            ]
        );

        // Akun demo hanya boleh dibuat di local/testing. Seeder production
        // tetap menyiapkan role, institusi, dan achievement tanpa password demo.
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        // Helper: buat user dengan status & email verified.
        $makeUser = function (array $attrs, array $roles, string $status) use ($mahasiswaRole, $dosenRole, $adminRole, $systemAdminRole) {
            $user = User::firstOrCreate(
                ['email' => $attrs['email']],
                array_merge($attrs, [
                    'password' => Hash::make('password'),
                    'registration_status' => $status,
                    'email_verified_at' => now(),
                ])
            );
            $user->syncRoles($roles);
            return $user;
        };

        // System Admin (role tertinggi — mengelola admin lain & konfigurasi sistem).
        $systemAdmin = $makeUser(
            ['email' => 'systemadmin@example.com', 'name' => 'System Administrator', 'identifier' => 'SYS001'],
            [$systemAdminRole],
            'approved'
        );

        // Admin + dosen dalam satu akun (multi-role), NIDN sebagai identifier.
        $adminDosen = $makeUser(
            ['email' => 'admin@example.com', 'name' => 'Ir. Admin Utama, M.T.', 'identifier' => '0001010101', 'nidn' => '0001010101'],
            [$adminRole, $dosenRole],
            'approved'
        );

        // Administrator khusus (role admin saja).
        $administrator = $makeUser(
            ['email' => 'administrator@example.com', 'name' => 'Administrator Sistem', 'identifier' => 'ADM001'],
            [$adminRole],
            'approved'
        );

        // Dosen pembimbing kedua (opsional).
        $dosen2 = $makeUser(
            ['email' => 'dosen2@example.com', 'name' => 'Dr. Dosen Dua, S.T., M.T.', 'identifier' => '0002020202', 'nidn' => '0002020202'],
            [$dosenRole],
            'approved'
        );

        // Dosen demo tambahan (untuk demo penguji & grup/cross-link).
        $dosen3 = $makeUser(
            ['email' => 'dosen3@example.com', 'name' => 'Dr. Dosen Tiga, S.Kom., M.Kom.', 'identifier' => '0003030303', 'nidn' => '0003030303'],
            [$dosenRole],
            'approved'
        );

        $dosen4 = $makeUser(
            ['email' => 'dosen4@example.com', 'name' => 'Dr. Dosen Empat, S.T., M.T.', 'identifier' => '0004040404', 'nidn' => '0004040404'],
            [$dosenRole],
            'approved'
        );

        // ===============================================================
        // Mahasiswa TA (verified) — sudah punya program + dosen.
        // ===============================================================
        $mahasiswa = $makeUser(
            ['email' => 'mahasiswa@example.com', 'name' => 'Mahasiswa Contoh', 'identifier' => '200401001', 'whatsapp' => '6281234567890'],
            [$mahasiswaRole],
            'verified'
        );

        // Data pokok TA mahasiswa (fase proposal + penguji untuk demo seminar submission).
        MahasiswaTa::firstOrCreate(
            ['user_id' => $mahasiswa->id, 'jenis' => MahasiswaTa::JENIS_TA],
            [
                'judul_ta' => 'Perancangan Sistem Pengolahan Air Limbah Domestik Terpusat di Kawasan Permukiman',
                'pembimbing_1_id' => $adminDosen->id,
                'pembimbing_2_id' => $dosen2->id,
                'penguji_1_id' => $dosen3->id,
                'penguji_2_id' => $dosen4->id,
                'target_sesi' => 7,
                'fase' => 'proposal',
                'status_ta' => MahasiswaTa::STATUS_AKTIF,
            ]
        );

        // ===============================================================
        // Akun demo mahasiswa KP (Kerja Praktek) kelompok.
        // ===============================================================
        $mahasiswaKp1 = $makeUser(
            ['email' => 'mahasiswa_kp@example.com', 'name' => 'Mahasiswa KP Satu', 'identifier' => '200401002', 'whatsapp' => '6281234567891'],
            [$mahasiswaRole],
            'verified'
        );

        $mahasiswaKp2 = $makeUser(
            ['email' => 'mahasiswa_kp2@example.com', 'name' => 'Mahasiswa KP Dua', 'identifier' => '200401003', 'whatsapp' => '6281234567892'],
            [$mahasiswaRole],
            'verified'
        );

        // Program KP kelompok (pemilik = mahasiswaKp1, anggota = mahasiswaKp2).
        $kp = MahasiswaTa::firstOrCreate(
            ['user_id' => $mahasiswaKp1->id, 'jenis' => MahasiswaTa::JENIS_KP],
            [
                'tempat_kp' => 'PT. Teknologi Nusantara',
                'pembimbing_1_id' => $adminDosen->id,
                'pembimbing_2_id' => $dosen2->id,
                'pembimbing_lapangan' => 'Bapak Rudi, S.T.',
                'target_sesi' => 7,
                'periode_mulai' => now()->subMonth(),
                'periode_selesai' => now()->addMonth(),
                'fase' => 'pelaksanaan',
                'status_ta' => MahasiswaTa::STATUS_AKTIF,
            ]
        );

        // Anggota kelompok kedua (demo fitur KP kelompok).
        if (! $kp->members()->where('user_id', $mahasiswaKp2->id)->exists()) {
            $kp->members()->attach($mahasiswaKp2->id);
        }

        // Contoh logbook harian KP.
        LogbookHarianKp::firstOrCreate(
            ['mahasiswa_ta_id' => $kp->id, 'tanggal' => now()->subDay()->toDateString()],
            [
                'kegiatan' => 'Mengikuti briefing dan mempelajari alur kerja divisi IT di PT. Teknologi Nusantara.',
                'kendala' => 'Perlu adaptasi dengan tools internal perusahaan.',
                'created_by' => $mahasiswaKp1->id,
            ]
        );

        // ===============================================================
        // Mahasiswa active (belum pilih dosen) — demo alur baru.
        // ===============================================================
        $mahasiswaActive = $makeUser(
            ['email' => 'mahasiswa_active@example.com', 'name' => 'Mahasiswa Aktif', 'identifier' => '200401004', 'whatsapp' => '6281234567893'],
            [$mahasiswaRole],
            'active'
        );

        // ===============================================================
        // Demo direktori organisasi & grup dosen.
        // ===============================================================
        $directory = app(OrganizationalDirectoryService::class);

        // 1. Buat universitas demo + struktur hierarkis.
        $univ = $directory->findOrCreateUniversity('Universitas Nusantara', '001001');
        $fakultas = $directory->findOrCreateFaculty($univ, 'Fakultas Teknik');
        $departemen = $directory->findOrCreateDepartment($fakultas, 'Departemen Teknik Informatika');
        $prodi = $directory->findOrCreateStudyProgram($departemen, 'S1 Teknik Informatika', '55201');

        // 2. Hubungkan dosen yang sudah ada ke universitas.
        $directory->attachUserToUniversity($adminDosen, $univ, $fakultas, $departemen, $prodi, true);
        $directory->attachUserToUniversity($dosen2, $univ, $fakultas, $departemen, $prodi, true);

        // 3. Hubungkan dosen demo tambahan ke universitas.
        $directory->attachUserToUniversity($dosen3, $univ, $fakultas, $departemen, $prodi, true);
        $directory->attachUserToUniversity($dosen4, $univ, $fakultas, $departemen, $prodi, true);

        // 4. Buat grup demo + anggota (adminDosen owner, dosen2, dosen3 & dosen4 approved).
        $group = Group::firstOrCreate(
            ['name' => 'Dosen Teknik Informatika Universitas Nusantara'],
            [
                'level' => 'prodi',
                'university_id' => $univ->id,
                'faculty_id' => $fakultas->id,
                'department_id' => $departemen->id,
                'study_program_id' => $prodi->id,
                'created_by' => $adminDosen->id,
            ]
        );

        foreach ([$adminDosen, $dosen2, $dosen3, $dosen4] as $i => $member) {
            GroupMember::firstOrCreate(
                ['group_id' => $group->id, 'user_id' => $member->id],
                [
                    'status' => 'approved',
                    'role' => $i === 0 ? 'owner' : 'member',
                ]
            );
        }
    }

    /**
     * Sinkronkan permission ke role (konsisten dengan migration permissions).
     */
    private function syncPermissions(): void
    {
        $permissions = [
            'logbook.create', 'logbook.review',
            'workspace.upload', 'workspace.delete', 'workspace.manage-others',
            'export.pdf', 'export.excel', 'import.excel',
            'seminar.submit', 'seminar.review',
            'finalization.submit', 'finalization.approve',
            'sidang.record',
            'announcement.create', 'chat.send',
            'admin.users', 'admin.tas', 'admin.sidangs', 'admin.institution', 'admin.bulk-review',
            'storage.manage',
            'groups.create', 'groups.invite',
            'approval.manage',
            'system.admins', 'system.plans',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $systemAdmin = Role::findOrCreate('system_admin');
        $admin = Role::findOrCreate('admin');
        $dosen = Role::findOrCreate('dosen');
        $mahasiswa = Role::findOrCreate('mahasiswa');

        $systemAdmin->syncPermissions($permissions);

        $admin->syncPermissions(array_values(array_filter(
            $permissions,
            fn ($p) => !str_starts_with($p, 'system.')
        )));

        $dosen->syncPermissions([
            'logbook.review',
            'workspace.upload', 'workspace.delete', 'workspace.manage-others',
            'export.pdf', 'export.excel',
            'seminar.review',
            'finalization.approve',
            'sidang.record',
            'announcement.create', 'chat.send',
            'storage.manage',
            'groups.create', 'groups.invite',
            'approval.manage',
        ]);

        $mahasiswa->syncPermissions([
            'logbook.create',
            'workspace.upload', 'workspace.delete',
            'seminar.submit',
            'finalization.submit',
            'chat.send',
        ]);
    }
}
