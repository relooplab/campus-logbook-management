<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

/**
 * Verifikasi halaman Pengaturan Autentikasi di panel system admin:
 * toggle "Wajib Verifikasi Email" + form SMTP.
 */
class SystemSettingsTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private function systemAdmin(): User
    {
        Role::firstOrCreate(['name' => 'system_admin', 'guard_name' => 'web']);
        $uid = uniqid();
        $sys = User::create([
            'name' => 'Sys Admin Settings', 'email' => "sys-settings-{$uid}@audit.test",
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => "SYS-SET-{$uid}", 'whatsapp' => '628',
        ]);
        $sys->assignRole('system_admin');

        return $sys;
    }

    public function test_system_admin_can_view_settings_page(): void
    {
        $response = $this->actingAs($this->systemAdmin())
            ->get(route('admin.system.settings'));

        $response->assertOk();
        $response->assertSee('Pengaturan Autentikasi');
        $response->assertSee('Verifikasi Email');
    }

    public function test_non_system_admin_cannot_access_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.system.settings'));

        $response->assertForbidden();
    }

    public function test_override_tidak_persists(): void
    {
        $sys = $this->systemAdmin();

        $response = $this->actingAs($sys)
            ->post(route('admin.system.settings.update'), [
                'email_verification_override' => 'tidak',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertFalse(
            (bool) Institution::active()->fresh()->email_verification_override,
            'Override "tidak" harus tersimpan sebagai false.'
        );
    }

    public function test_smtp_fields_optional(): void
    {
        // SMTP kini opsional — menyimpan override tanpa SMTP harus sukses.
        $sys = $this->systemAdmin();

        $response = $this->actingAs($sys)
            ->post(route('admin.system.settings.update'), [
                'email_verification_override' => 'wajib',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_override_wajib_with_smtp_succeeds(): void
    {
        $sys = $this->systemAdmin();

        $response = $this->actingAs($sys)
            ->post(route('admin.system.settings.update'), [
                'email_verification_override' => 'wajib',
                'mail_mailer' => 'log',
                'mail_host' => 'smtp.test.local',
                'mail_port' => 1025,
                'mail_username' => 'user@test.local',
                'mail_password' => 'secret',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'no-reply@test.local',
                'mail_from_name' => 'Test Sender',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $inst = Institution::active()->fresh();
        $this->assertSame(1, $inst->email_verification_override, 'Override "wajib" harus tersimpan true.');
        $this->assertSame('log', $inst->mail_mailer);
        $this->assertSame('smtp.test.local', $inst->mail_host);
        $this->assertSame(1025, (int) $inst->mail_port);
    }

    public function test_smtp_form_always_visible(): void
    {
        $sys = $this->systemAdmin();

        $response = $this->actingAs($sys)->get(route('admin.system.settings'));
        $response->assertOk();
        // Form SMTP selalu tampil (tanpa class hidden) & ada opsi Auto.
        $this->assertStringContainsString('id="smtp-form" class="space-y-4 pt-2 border-t border-border"', $response->getContent());
        $this->assertStringContainsString('Auto — ikuti SMTP', $response->getContent());
    }
}
