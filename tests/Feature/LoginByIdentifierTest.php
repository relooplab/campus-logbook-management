<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifikasi login & lupa password bisa menggunakan NIM (mahasiswa) / NIDN
 * (dosen) selain email.
 */
class LoginByIdentifierTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        foreach (['dosen', 'mahasiswa'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->mahasiswa = User::create([
            'name' => 'Mhs Ident', 'email' => 'mhs-ident-'.uniqid().'@audit.test',
            'password' => Hash::make('secret123'), 'registration_status' => 'active',
            'nim' => 'NIM-'.uniqid(), 'whatsapp' => '628',
        ]);
        $this->mahasiswa->assignRole('mahasiswa');

        $this->dosen = User::create([
            'name' => 'Dsn Ident', 'email' => 'dsn-ident-'.uniqid().'@audit.test',
            'password' => Hash::make('secret123'), 'registration_status' => 'active',
            'nidn' => 'NIDN-'.uniqid(), 'whatsapp' => '6281',
        ]);
        $this->dosen->assignRole('dosen');
    }

    public function test_mahasiswa_can_login_with_nim(): void
    {
        $response = $this->post(route('login.attempt'), [
            'email' => $this->mahasiswa->nim,
            'password' => 'secret123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->mahasiswa);
    }

    public function test_dosen_can_login_with_nidn(): void
    {
        $response = $this->post(route('login.attempt'), [
            'email' => $this->dosen->nidn,
            'password' => 'secret123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->dosen);
    }

    public function test_login_with_unknown_identifier_fails_with_generic_message(): void
    {
        $response = $this->post(route('login.attempt'), [
            'email' => 'NIM-TAK-DIKENAL-'.uniqid(),
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_with_identifier_and_wrong_password_fails(): void
    {
        $response = $this->post(route('login.attempt'), [
            'email' => $this->mahasiswa->nim,
            'password' => 'salah',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_mahasiswa_can_request_password_reset_with_nim(): void
    {
        $response = $this->post(route('password.email'), ['email' => $this->mahasiswa->nim]);

        $response->assertSessionHas('status');
        $this->assertTrue(
            DB::table('password_reset_tokens')->where('email', $this->mahasiswa->email)->exists()
        );
    }

    public function test_dosen_can_request_password_reset_with_nidn(): void
    {
        $response = $this->post(route('password.email'), ['email' => $this->dosen->nidn]);

        $response->assertSessionHas('status');
        $this->assertTrue(
            DB::table('password_reset_tokens')->where('email', $this->dosen->email)->exists()
        );
    }
}
