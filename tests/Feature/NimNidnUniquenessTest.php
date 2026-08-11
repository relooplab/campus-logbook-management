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

    public function test_register_rejects_duplicate_nidn(): void
    {
        $dosen = new User([
            'name' => 'Dosen Existing', 'email' => 'de-'.uniqid().'@audit.test',
            'password' => bcrypt('x'), 'registration_status' => 'active', 'whatsapp' => '628',
        ]);
        $dosen->nidn = '0000000999';
        $dosen->save();

        $response = $this->post(route('register'), [
            'name' => 'Dosen Baru',
            'email' => 'db-'.uniqid().'@audit.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'dosen',
            'nidn' => '0000000999', // bentrok dengan NIDN dosen existing
        ]);

        $response->assertSessionHasErrors('nidn');
    }

    public function test_register_rejects_nim_matching_existing_nidn(): void
    {
        $dosen = new User([
            'name' => 'Dosen X', 'email' => 'dx-'.uniqid().'@audit.test',
            'password' => bcrypt('x'), 'registration_status' => 'active', 'whatsapp' => '628',
        ]);
        $dosen->nidn = '0000000777';
        $dosen->save();

        // Mahasiswa daftar pakai NIM yang = NIDN dosen existing -> tolak lintas kolom.
        $response = $this->post(route('register'), [
            'name' => 'Mhs Baru',
            'email' => 'mb-'.uniqid().'@audit.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'mahasiswa',
            'nim' => '0000000777',
        ]);

        $response->assertSessionHasErrors('nim');
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
}
