<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use App\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fitur "Ubah Email" (self-service):
 *  - Wajib konfirmasi password saat ini.
 *  - Saat verifikasi email wajib, alamat baru di-reset & minta verifikasi ulang.
 *  - Bisa diakses user yang belum verified (halaman verifikasi / salah alamat).
 */
class EmailChangeTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $email = 'before@test.com'): User
    {
        Role::firstOrCreate(['name' => 'mahasiswa', 'guard_name' => 'web']);
        $u = User::create([
            'name' => 'Email Change',
            'email' => $email,
            'password' => bcrypt('secret123'),
            'registration_status' => 'active',
            'nim' => 'NIM-EC-'.uniqid(),
            'whatsapp' => '628',
        ]);
        // email_verified_at bukan fillable — set langsung & simpan.
        $u->email_verified_at = now();
        $u->save();
        $u->assignRole('mahasiswa');

        return $u;
    }

    private function setVerification(?bool $override): void
    {
        $inst = Institution::active();
        $inst->email_verification_override = $override; // null=Auto, true=Wajib, false=Tidak
        $inst->save();
        Institution::flush();
    }

    public function test_change_email_when_verification_off(): void
    {
        $this->setVerification(false);
        $u = $this->makeUser();

        $r = $this->actingAs($u)->put(route('profile.email'), [
            'email' => 'new@test.com',
            'email_confirmation' => 'new@test.com',
            'current_password' => 'secret123',
        ]);

        $r->assertSessionHas('success');
        $this->assertSame('new@test.com', $u->fresh()->email);
        $this->assertNotNull($u->fresh()->email_verified_at, 'Saat verifikasi tidak wajib, status verified dipertahankan.');
    }

    public function test_change_email_wrong_password_rejected(): void
    {
        $this->setVerification(false);
        $u = $this->makeUser();

        $r = $this->actingAs($u)->put(route('profile.email'), [
            'email' => 'new@test.com',
            'email_confirmation' => 'new@test.com',
            'current_password' => 'wrongpass',
        ]);

        $r->assertSessionHasErrors('current_password');
        $this->assertSame('before@test.com', $u->fresh()->email);
    }

    public function test_change_email_when_verification_on_resets_and_sends(): void
    {
        $this->setVerification(true);
        $u = $this->makeUser();

        Notification::fake();

        $r = $this->actingAs($u)->put(route('profile.email'), [
            'email' => 'new@test.com',
            'email_confirmation' => 'new@test.com',
            'current_password' => 'secret123',
        ]);

        $r->assertSessionHas('success');
        $fresh = $u->fresh();
        $this->assertSame('new@test.com', $fresh->email);
        $this->assertNull($fresh->email_verified_at, 'Alamat baru harus diverifikasi ulang.');
        Notification::assertSentTo($fresh, VerifyEmail::class);
    }

    public function test_email_must_be_unique(): void
    {
        $this->setVerification(false);
        $u = $this->makeUser('me@test.com');
        $this->makeUser('taken@test.com');

        $r = $this->actingAs($u)->put(route('profile.email'), [
            'email' => 'taken@test.com',
            'email_confirmation' => 'taken@test.com',
            'current_password' => 'secret123',
        ]);

        $r->assertSessionHasErrors('email');
        $this->assertSame('me@test.com', $u->fresh()->email);
    }

    public function test_unverified_user_can_change_email(): void
    {
        $this->setVerification(true);
        $u = $this->makeUser();
        $u->update(['email_verified_at' => null]); // belum verified

        $r = $this->actingAs($u)->put(route('profile.email'), [
            'email' => 'fixed@test.com',
            'email_confirmation' => 'fixed@test.com',
            'current_password' => 'secret123',
        ]);

        $r->assertSessionHas('success');
        $this->assertSame('fixed@test.com', $u->fresh()->email);
    }
}