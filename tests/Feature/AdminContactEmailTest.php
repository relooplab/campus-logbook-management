<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

/**
 * Verifikasi email kontak admin (admin_contact_email) beserta tampilannya:
 * resolusi default global vs override institusi, panel system admin & admin
 * institusi, lalu informasi bantuan di halaman login, register, dan profil.
 */
class AdminContactEmailTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private function systemAdmin(): User
    {
        Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);
        $uid = uniqid();
        $sys = User::create([
            'name' => 'Sys Admin Contact', 'email' => "sys-contact-{$uid}@audit.test",
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => "SYS-{$uid}", 'whatsapp' => '628',
        ]);
        $sys->assignRole('system_admin');
        return $sys;
    }

    private function setGlobalContact(string $email): void
    {
        $inst = Institution::active();
        $inst->admin_contact_email = $email;
        $inst->save();
        Institution::flush();
    }

    public function test_resolution_prefers_institution_override_over_global(): void
    {
        $this->setGlobalContact('global@kampus.test');

        $inst = Institution::create([
            'app_name' => 'T', 'institution_name' => 'Inst Override '.uniqid(),
            'email' => 'ov-'.uniqid().'@test.com', 'admin_contact_email' => 'inst@a.test',
        ]);
        $user = User::create([
            'name' => 'U', 'email' => 'u-contact-'.uniqid().'@audit.test',
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => 'U-'.uniqid(), 'institution_id' => $inst->id,
        ]);

        // Guest / user tanpa institusi → default global.
        $this->assertSame('global@kampus.test', Institution::adminContactEmailFor(null));
        // User dengan institusi yang meng-set override → dipakai override.
        $this->assertSame('inst@a.test', Institution::adminContactEmailFor($user));
    }

    public function test_system_admin_can_set_global_contact_email(): void
    {
        $sys = $this->systemAdmin();
        $this->actingAs($sys)->post(route('admin.system.settings.update'), [
            'admin_contact_email' => 'sys-default@kampus.test',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('sys-default@kampus.test', Institution::active()->fresh()->admin_contact_email);
    }

    public function test_institution_admin_can_set_institution_contact_email(): void
    {
        $inst = Institution::create([
            'app_name' => 'T', 'institution_name' => 'Inst A '.uniqid(),
            'email' => 'ia-'.uniqid().'@test.com',
        ]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::create([
            'name' => 'Admin A', 'email' => 'ia-admin-'.uniqid().'@audit.test',
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => 'IA-'.uniqid(), 'institution_id' => $inst->id,
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.institution.update'), [
            'app_name' => 'T', 'institution_name' => $inst->institution_name,
            'faculty' => null, 'study_program' => null, 'address' => null, 'city' => null,
            'phone' => null, 'email' => 'ia-@test.com', 'website' => null,
            'footer_note' => null, 'max_upload_size_mb' => 10,
            'allowed_file_types' => 'pdf', 'seminar_hardcopy_note' => null,
            'admin_contact_email' => 'inst-admin@a.test',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('inst-admin@a.test', $inst->fresh()->admin_contact_email);
    }

    public function test_login_page_shows_global_contact_email(): void
    {
        $this->setGlobalContact('help@login.test');
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('help@login.test');
    }

    public function test_register_page_shows_global_contact_email(): void
    {
        $this->setGlobalContact('help@register.test');
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('help@register.test');
    }

    public function test_dosen_profile_shows_nidn_readonly_and_contact_email(): void
    {
        Role::firstOrCreate(['name' => 'dosen', 'guard_name' => 'web']);
        $this->setGlobalContact('help@profil.test');

        $dosen = User::create([
            'name' => 'Dosen Profil', 'email' => 'dp-'.uniqid().'@audit.test',
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => 'DP-'.uniqid(), 'whatsapp' => '628', 'nidn' => '1234567890',
        ]);
        $dosen->assignRole('dosen');

        $this->actingAs($dosen)->get(route('profile.index'))
            ->assertOk()
            ->assertSee('1234567890')          // NIDN read-only tampil
            ->assertSee('NIDN')                // label field
            ->assertSee('help@profil.test');   // info hubungi admin
    }
}
