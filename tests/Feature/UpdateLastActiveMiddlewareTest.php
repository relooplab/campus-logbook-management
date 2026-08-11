<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MahasiswaTa;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regresi: middleware UpdateLastActive harus menulis `last_active_at` pada
 * request web yang ter-autentikasi (dipasang di grup `web` agar Auth::user()
 * tersedia — bukan global yang berjalan sebelum session).
 */
class UpdateLastActiveMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    private function makeMahasiswa(): User
    {
        $u = User::create([
            'name' => 'Last Active Mhs',
            'email' => 'last-active-'.uniqid().'@t.test',
            'password' => bcrypt('password'),
            'registration_status' => 'active',
            'nim' => 'NIM'.substr(md5(uniqid()), 0, 8),
            'whatsapp' => '6281234567890',
            'last_active_at' => null,
        ]);
        $u->assignRole('mahasiswa');

        return $u;
    }

    public function test_request_terautentikasi_menulis_last_active_at(): void
    {
        $user = $this->makeMahasiswa();

        $this->assertNull($user->fresh()->last_active_at);

        // Request web yang login: middleware grup `web` harus menulis last_active_at.
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertNotNull($user->fresh()->last_active_at);
    }

    public function test_throttling_tidak_menulis_setiap_request(): void
    {
        $user = $this->makeMahasiswa();
        // Set last_active_at baru-baru ini (dalam ambang 60 detik).
        $user->forceFill(['last_active_at' => now()->subSeconds(10)])->save();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        // Tidak berubah karena masih dalam window throttling (menit yang sama).
        $this->assertSame(
            $user->last_active_at->getTimestamp(),
            $user->fresh()->last_active_at->getTimestamp()
        );
    }

    public function test_method_helpers_last_active_status(): void
    {
        $user = $this->makeMahasiswa();

        $this->assertSame('never', $user->lastActiveStatus());

        $user->forceFill(['last_active_at' => now()])->save();
        $this->assertTrue($user->fresh()->isOnline());
        $this->assertSame('online', $user->fresh()->lastActiveStatus());

        $user->forceFill(['last_active_at' => now()->subMinutes(30)])->save();
        $this->assertFalse($user->fresh()->isOnline());
        $this->assertSame('offline', $user->fresh()->lastActiveStatus());
    }
}
