<?php

namespace Tests\Feature;

use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class StudentApprovalTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    public function test_dosen_can_invite_mahasiswa_by_email_only(): void
    {
        $response = $this->actingAs($this->dosen)->post(route('approval.invite'), [
            'email' => 'budiman@mail.com',
        ]);

        $response->assertRedirect(route('approval.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'budiman@mail.com',
            'registration_status' => 'pending',
        ]);

        $user = User::where('email', 'budiman@mail.com')->first();
        $this->assertTrue($user->hasRole('mahasiswa'));
        $this->assertEquals('Budiman', $user->name);
    }

    public function test_invite_rejects_duplicate_email(): void
    {
        User::create([
            'name' => 'Existing',
            'email' => 'existing@mail.com',
            'password' => bcrypt('password'),
            'registration_status' => 'approved',
        ]);

        $this->actingAs($this->dosen)
            ->post(route('approval.invite'), ['email' => 'existing@mail.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_dosen_can_approve_invited_mahasiswa_and_assign_role(): void
    {
        $mhs = User::create([
            'name' => 'Budiman',
            'email' => 'budiman2@mail.com',
            'password' => bcrypt('password'),
            'registration_status' => 'pending',
        ]);
        $mhs->syncRoles(['mahasiswa']);

        $this->actingAs($this->dosen)->post(route('approval.approve', $mhs), [
            'judul_ta' => 'Sistem Informasi',
            'role_dosen' => 'pembimbing_1',
            'target_sesi' => 7,
        ])->assertRedirect(route('approval.index'));

        $this->assertDatabaseHas('users', [
            'id' => $mhs->id,
            'registration_status' => 'approved',
        ]);

        $this->assertDatabaseHas('mahasiswa_ta', [
            'user_id' => $mhs->id,
            'pembimbing_1_id' => $this->dosen->id,
            'judul_ta' => 'Sistem Informasi',
        ]);
    }
}