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
            'identifier' => "SYS-SET-{$uid}", 'whatsapp' => '628',
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
        $response->assertSee('Wajib Verifikasi Email');
    }

    public function test_non_system_admin_cannot_access_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.system.settings'));

        $response->assertForbidden();
    }

    public function test_toggle_off_persists(): void
    {
        $sys = $this->systemAdmin();

        $response = $this->actingAs($sys)
            ->post(route('admin.system.settings.update'), [
                'email_verification_required' => 0,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertFalse(
            (bool) Institution::active()->fresh()->email_verification_required,
            'Toggle OFF harus tersimpan sebagai false.'
        );
    }

    public function test_toggle_on_requires_smtp_fields(): void
    {
        $sys = $this->systemAdmin();

        $response = $this->actingAs($sys)
            ->post(route('admin.system.settings.update'), [
                'email_verification_required' => 1,
                // SMTP fields sengaja kosong -> harus error.
            ]);

        $response->assertSessionHasErrors(['mail_mailer', 'mail_host', 'mail_port', 'mail_from_address', 'mail_from_name']);
    }

    public function test_toggle_on_with_smtp_succeeds(): void
    {
        $sys = $this->systemAdmin();

        $response = $this->actingAs($sys)
            ->post(route('admin.system.settings.update'), [
                'email_verification_required' => 1,
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
        $this->assertTrue((bool) $inst->email_verification_required);
        $this->assertSame('log', $inst->mail_mailer);
        $this->assertSame('smtp.test.local', $inst->mail_host);
        $this->assertSame(1025, (int) $inst->mail_port);
    }

    public function test_smtp_form_hidden_when_toggle_off(): void
    {
        $sys = $this->systemAdmin();
        Institution::active()->update(['email_verification_required' => false]);
        Institution::flush();

        $response = $this->actingAs($sys)->get(route('admin.system.settings'));
        $response->assertOk();
        // smtp-form hidden class
        $this->assertStringContainsString('id="smtp-form" class="space-y-4 pt-2 border-t border-border hidden"', $response->getContent());
    }

    public function test_smtp_form_visible_when_toggle_on(): void
    {
        $sys = $this->systemAdmin();
        Institution::active()->update(['email_verification_required' => true]);
        Institution::flush();

        $response = $this->actingAs($sys)->get(route('admin.system.settings'));
        $response->assertOk();
        $this->assertStringContainsString('id="smtp-form" class="space-y-4 pt-2 border-t border-border "', $response->getContent());
    }
}
