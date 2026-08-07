<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Akun demo ringkas untuk SEMUA role.
 *
 * Akun existing yang konflik (email / identifier / nidn) dihapus terlebih
 * dahulu agar tidak gagal karena constraint unique, lalu dibuat ulang dengan
 * password seragam: "password".
 */
class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['system_admin', 'admin', 'dosen', 'mahasiswa'] as $roleName) {
            Role::findOrCreate($roleName);
        }

        $accounts = [
            'system_admin' => [
                'email' => 'systemadmin@example.com',
                'name' => 'System Administrator',
                'identifier' => 'DEMO-SYS',
                'status' => 'approved',
            ],
            'admin' => [
                'email' => 'admin@example.com',
                'name' => 'Admin Utama',
                'identifier' => 'DEMO-ADM',
                'status' => 'approved',
            ],
            'dosen' => [
                'email' => 'dosen@example.com',
                'name' => 'Dosen Demo',
                'identifier' => 'DEMO-DOS',
                'nidn' => '0000000001',
                'status' => 'approved',
            ],
            'mahasiswa' => [
                'email' => 'mahasiswa@example.com',
                'name' => 'Mahasiswa Demo',
                'identifier' => 'DEMO-MHS',
                'whatsapp' => '628123456789',
                'status' => 'active',
            ],
        ];

        foreach ($accounts as $role => $attrs) {
            $status = $attrs['status'] ?? 'approved';
            unset($attrs['status']);

            // Hapus akun existing yang konflik (email / identifier / nidn).
            $conflictQuery = User::where('email', $attrs['email']);
            if (! empty($attrs['identifier'])) {
                $conflictQuery->orWhere('identifier', $attrs['identifier']);
            }
            if (! empty($attrs['nidn'])) {
                $conflictQuery->orWhere('nidn', $attrs['nidn']);
            }

            $conflictQuery->get()->each(function (User $u) use ($role, $attrs, $status) {
                // Jangan hapus user yang sudah punya TA/KP: cascade akan menghapus
                // data bimbingan demo. Cukup update in-place + pastikan role.
                if ($u->mahasiswaPrograms()->exists()) {
                    $u->forceFill([
                        'name' => $attrs['name'],
                        'nidn' => $attrs['nidn'] ?? $u->nidn,
                        'identifier' => $attrs['identifier'] ?? $u->identifier,
                        'whatsapp' => $attrs['whatsapp'] ?? $u->whatsapp,
                        'registration_status' => $status,
                        'email_verified_at' => now(),
                    ])->save();
                    $u->syncRoles([$role]);

                    return;
                }

                $u->syncRoles([]);
                $u->delete();
            });

            $user = User::forceCreate(array_merge($attrs, [
                'password' => Hash::make('password'),
                'registration_status' => $status,
                'email_verified_at' => now(),
            ]));

            $user->syncRoles([$role]);
        }

        // Dosen demo wajib punya afiliasi lengkap (gate onboarding) agar bisa login & pakai fitur.
        $dosenDemo = User::where('email', 'dosen@example.com')->first();
        if ($dosenDemo) {
            $directory = app(\App\Services\OrganizationalDirectoryService::class);
            $u = $directory->findOrCreateUniversity('Universitas Demo');
            $f = $directory->findOrCreateFaculty($u, 'Fakultas Teknik');
            $d = $directory->findOrCreateDepartment($f, 'Departemen Teknik Informatika');
            $p = $directory->findOrCreateStudyProgram($d, 'S1 Teknik Informatika', '51001');
            $directory->attachUserToUniversity($dosenDemo, $u, $f, $d, $p, true);
        }

        $this->command->info('Akun demo dibuat untuk semua role (password: "password"):');
        foreach ($accounts as $role => $attrs) {
            $this->command->line(sprintf('  - %-14s %s', $role, $attrs['email']));
        }
    }
}
