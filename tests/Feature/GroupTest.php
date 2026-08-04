<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\University;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class GroupTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private OrganizationalDirectoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrganizationalDirectoryService::class);
    }

    public function test_dosen_can_create_group(): void
    {
        $university = $this->service->findOrCreateUniversity('Universitas Test');
        $this->service->attachUserToUniversity($this->dosen, $university, isPrimary: true);

        $this->actingAs($this->dosen)->post(route('groups.store'), [
            'name' => 'Dosen Teknik Informatika',
            'level' => 'prodi',
            'university_id' => $university->id,
        ])->assertRedirect(route('groups.index'));

        $this->assertDatabaseHas('groups', [
            'name' => 'Dosen Teknik Informatika',
            'university_id' => $university->id,
            'created_by' => $this->dosen->id,
        ]);

        $group = Group::where('name', 'Dosen Teknik Informatika')->first();
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $this->dosen->id,
            'status' => 'approved',
            'role' => 'owner',
        ]);
    }

    public function test_has_direct_relation_via_shared_group(): void
    {
        // Dosen baru yang tidak punya TA bersama (hindari setup AuditSmokeTest).
        $dosenA = User::create(['name' => 'Dosen A', 'email' => 'dosen-a@test.com', 'password' => bcrypt('password')]);
        $dosenB = User::create(['name' => 'Dosen B', 'email' => 'dosen-b@test.com', 'password' => bcrypt('password')]);
        $dosenA->assignRole('dosen');
        $dosenB->assignRole('dosen');

        $university = $this->service->findOrCreateUniversity('Universitas Test 3');
        $this->service->attachUserToUniversity($dosenA, $university, isPrimary: true);
        $this->service->attachUserToUniversity($dosenB, $university, isPrimary: true);

        // Belum ada hubungan -> false.
        $this->assertFalse($dosenA->hasDirectRelation($dosenB));

        // Buat grup & dosenB approve.
        $this->actingAs($dosenA)->post(route('groups.store'), [
            'name' => 'Grup Relasi',
            'level' => 'universitas',
            'university_id' => $university->id,
        ])->assertRedirect(route('groups.index'));

        $group = Group::where('name', 'Grup Relasi')->first();
        $this->actingAs($dosenA)->post(route('groups.invite', $group), ['user_id' => $dosenB->id])->assertRedirect();
        $this->actingAs($dosenB)->post(route('groups.approve', $group))->assertRedirect();

        // Setelah approve -> true.
        $this->assertTrue($dosenA->hasDirectRelation($dosenB));
    }

    public function test_dosen_can_invite_colleague_and_approve(): void
    {
        $university = $this->service->findOrCreateUniversity('Universitas Test 2');
        $this->service->attachUserToUniversity($this->dosen, $university, isPrimary: true);
        $this->service->attachUserToUniversity($this->dosen2, $university, isPrimary: true);

        // Dosen membuat grup.
        $this->actingAs($this->dosen)->post(route('groups.store'), [
            'name' => 'Grup Kolaborasi',
            'level' => 'universitas',
            'university_id' => $university->id,
        ])->assertRedirect(route('groups.index'));

        $group = Group::where('name', 'Grup Kolaborasi')->first();

        // Dosen mengundang dosen2.
        $this->actingAs($this->dosen)->post(route('groups.invite', $group), [
            'user_id' => $this->dosen2->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $this->dosen2->id,
            'status' => 'pending',
        ]);

        // Dosen2 menyetujui undangan.
        $this->actingAs($this->dosen2)->post(route('groups.approve', $group))->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $this->dosen2->id,
            'status' => 'approved',
        ]);
    }
}