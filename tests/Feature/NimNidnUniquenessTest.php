<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

/**
 * Fondasi identitas: NIM (users.nim) khusus mahasiswa, NIDN (users.nidn)
 * khusus dosen. Masing-masing unik, dan satu nilai tidak boleh dipakai
 * sebagai NIM & NIDN sekaligus (lintas kolom).
 */
class NimNidnUniquenessTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'dosen', 'mahasiswa'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
    }

    private function systemAdmin(): User
    {
        $uid = uniqid();
        $s = User::create([
            'name' => 'Sys Admin', 'email' => "sys-nim-{$uid}@audit.test",
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => 'SYS-'.$uid, 'whatsapp' => '628',
        ]);
        $s->assignRole('system_admin');
        return $s;
    }

    public function test_identifier_is_unique_across_nim_and_nidn(): void
    {
        // Akun dosen dengan NIDN.
        $dosen = new User([
            'name' => 'Dosen A', 'email' => 'da-'.uniqid().'@audit.test',
            'password' => bcrypt('x'), 'registration_status' => 'active', 'whatsapp' => '628',
        ]);
        $dosen->nidn = '0000000123';
        $dosen->save();

        // Nilai yang sama tidak boleh dipakai sebagai NIM mahasiswa lain.
        $this->assertTrue(User::identifierIsTaken('0000000123'));
        $this->assertNotNull(User::findByIdentifier('0000000123'));
    }

    public function test_find_by_identifier_finds_nim_user(): void
    {
        $m = new User([
            'name' => 'Mhs A', 'email' => 'ma-'.uniqid().'@audit.test',
            'password' => bcrypt('x'), 'registration_status' => 'active', 'whatsapp' => '628',
        ]);
        $m->nim = '200401077';
        $m->save();

        $found = User::findByIdentifier('200401077');
        $this->assertNotNull($found);
        $this->assertSame($m->id, $found->id);
    }

    public function test_store_user_rejects_duplicate_nim(): void
    {
        $m = new User([
            'name' => 'Mhs Existing', 'email' => 'me-'.uniqid().'@audit.test',
            'password' => bcrypt('x'), 'registration_status' => 'active', 'whatsapp' => '628',
        ]);
        $m->nim = '2004010101';
        $m->save();

        $response = $this->actingAs($this->systemAdmin())
            ->post(route('admin.users.store'), [
                'name' => 'Mhs Dup',
                'email' => 'md-'.uniqid().'@audit.test',
                'password' => 'secret123',
                'roles' => ['mahasiswa'],
                'nim' => '2004010101',
            ]);

        $response->assertSessionHasErrors('nim');
    }

    /** Dosen pembantu untuk test pengisian NIDN via profil. */
    private function dosen(?string $nidn = null): User
    {
        Role::firstOrCreate(['name' => 'dosen', 'guard_name' => 'web']);

        $u = new User([
            'name' => 'Dosen Profil', 'email' => 'dosen-n-'.uniqid().'@audit.test',
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'whatsapp' => '628', 'email_verified_at' => now(),
        ]);
        $u->save();
        $u->assignRole('dosen');
        if ($nidn) {
            $u->forceFill(['nidn' => $nidn])->save();
        }

        return $u;
    }

    /** Register tidak lagi mengunci NIM/NIDN — identitas diisi setelah verifikasi email. */
    public function test_register_does_not_lock_nidn(): void
    {
        $email = 'dosen-reg-'.uniqid().'@audit.test';
        $response = $this->post(route('register'), [
            'name' => 'Dosen Reg',
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'dosen',
            'nidn' => '1234567890', // dikirim tapi tidak boleh dikunci di pendaftaran
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertNull($user->nidn, 'NIDN tidak boleh tersimpan saat register.');
    }

    public function test_dosen_can_set_nidn_once_in_profile(): void
    {
        $dosen = $this->dosen();
        $this->assertNull($dosen->nidn);

        $this->actingAs($dosen)->put(route('profile.update'), [
            'name' => $dosen->name, 'nidn' => '1234567890',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('1234567890', $dosen->fresh()->nidn);
    }

    public function test_dosen_cannot_change_nidn_after_set(): void
    {
        $dosen = $this->dosen('1234567890');

        $this->actingAs($dosen)->put(route('profile.update'), [
            'name' => $dosen->name, 'nidn' => '0987654321',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('1234567890', $dosen->fresh()->nidn, 'NIDN tidak boleh berubah lewat profil setelah terisi.');
    }

    public function test_dosen_nidn_must_be_10_digits_in_profile(): void
    {
        $dosen = $this->dosen();

        $this->actingAs($dosen)->put(route('profile.update'), [
            'name' => $dosen->name, 'nidn' => '12345',
        ])->assertSessionHasErrors('nidn');

        $this->assertNull($dosen->fresh()->nidn);
    }

    public function test_dosen_nidn_must_be_unique_globally_in_profile(): void
    {
        $other = $this->dosen('1111222233');

        $dosen = $this->dosen();
        $this->actingAs($dosen)->put(route('profile.update'), [
            'name' => $dosen->name, 'nidn' => '1111222233',
        ])->assertSessionHasErrors('nidn');

        $this->assertNull($dosen->fresh()->nidn);
    }
}
