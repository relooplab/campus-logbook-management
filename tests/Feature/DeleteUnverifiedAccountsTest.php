<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifikasi pembersihan akun yang tidak pernah verifikasi email
 * (`users:delete-unverified`): hapus akun unverified lama, tetapi JANGAN
 * menghapus akun baru/terverifikasi, dan JANGAN menghapus akun admin.
 */
class DeleteUnverifiedAccountsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(array $overrides = []): User
    {
        $verified = $overrides['email_verified_at'] ?? null;
        unset($overrides['email_verified_at']);

        // `email_verified_at` tidak ada di $fillable, jadi di-set via forceFill.
        $u = User::create(array_merge([
            'name' => 'U', 'email' => 'uv-'.uniqid().'@t.test',
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => 'NIM-'.uniqid(),
        ], $overrides));

        if ($verified !== null) {
            $u->forceFill(['email_verified_at' => $verified])->save();
        }

        return $u;
    }

    private function age(User $user, int $daysAgo): void
    {
        $user->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
    }

    public function test_deletes_old_unverified_account(): void
    {
        $u = $this->makeUser(['email_verified_at' => null]);
        $this->age($u, 10);

        $this->artisan('users:delete-unverified', ['--days' => 7])->assertSuccessful();

        $this->assertNull(User::find($u->id), 'Akun unverified lama harus dihapus.');
    }

    public function test_keeps_recent_unverified_account(): void
    {
        $u = $this->makeUser(['email_verified_at' => null]);
        $this->age($u, 1);

        $this->artisan('users:delete-unverified', ['--days' => 7])->assertSuccessful();

        $this->assertNotNull(User::find($u->id), 'Akun unverified baru (< 7 hari) tidak boleh dihapus.');
    }

    public function test_keeps_verified_account(): void
    {
        $u = $this->makeUser(['email_verified_at' => now()]);
        $this->age($u, 10);

        $this->artisan('users:delete-unverified', ['--days' => 7])->assertSuccessful();

        $this->assertNotNull(User::find($u->id), 'Akun terverifikasi tidak boleh dihapus.');
    }

    public function test_keeps_admin_even_if_old_and_unverified(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $u = $this->makeUser(['email_verified_at' => null]);
        $this->age($u, 10);
        $u->assignRole('admin');

        $this->artisan('users:delete-unverified', ['--days' => 7])->assertSuccessful();

        $this->assertNotNull(User::find($u->id), 'Akun admin tidak boleh dihapus otomatis.');
    }
}
