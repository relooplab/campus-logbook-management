<?php

namespace Tests\Feature;

use App\Models\MahasiswaTa;
use App\Models\User;
use App\Models\WorkspaceFile;
use App\Services\StorageUsageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Perbaikan & fitur baru:
 *  1. Dosen self-register (registration_status 'active') sebagai pembimbing 1
 *     tetap dibebani penyimpanan mahasiswa bimbingannya (fix dosenProgramIds).
 *  2. Pembimbing (atau admin) dapat langsung mengganti dosen penguji.
 *  3. Dosen pembimbing dapat membuka workspace mahasiswa (regresi).
 *  4. Profil akademik pending_approval menampilkan "menunggu persetujuan"
 *     (bukan ajakan "Pilih Dosen").
 */
class PengujiReplacementByPembimbingTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    // ------------------------------------------------------- 1. kuota dosen 'active'

    public function test_dosen_selfregistered_active_terbebani_storage_mahasiswa(): void
    {
        $dosen = User::create([
            'name' => 'Dosen Aktif',
            'email' => 'dosen-active-'.uniqid().'@t.test',
            'password' => bcrypt('password'),
            'nidn' => 'NIDN'.substr(md5(uniqid()), 0, 10),
            'registration_status' => 'active',
        ]);
        $dosen->assignRole('dosen');

        $mhs = User::create([
            'name' => 'Mhs Kuota',
            'email' => 'mhs-kuota-'.uniqid().'@t.test',
            'password' => bcrypt('password'),
            'nim' => 'NIM'.substr(md5(uniqid()), 0, 8),
            'whatsapp' => '628xxx',
            'registration_status' => 'active',
        ]);
        $mhs->assignRole('mahasiswa');

        $ta = MahasiswaTa::create([
            'user_id' => $mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $dosen->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
            'fase' => 'proposal',
        ]);

        WorkspaceFile::create([
            'mahasiswa_ta_id' => $ta->id,
            'uploaded_by' => $mhs->id,
            'original_name' => 'skripsi.pdf',
            'path' => 'workspace/'.$ta->id.'/skripsi.pdf',
            'mime_type' => 'application/pdf',
            'size' => 5000,
        ]);

        $svc = app(StorageUsageService::class);
        $baseline = $svc->totalBytes($dosen);

        WorkspaceFile::create([
            'mahasiswa_ta_id' => $ta->id,
            'uploaded_by' => $mhs->id,
            'original_name' => 'bab1.pdf',
            'path' => 'workspace/'.$ta->id.'/bab1.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
        ]);

        // File mahasiswa (1000 bytes) ikut terhitung ke kuota dosen.
        $this->assertSame($baseline + 1000, $svc->totalBytes($dosen));
    }

    // ------------------------------------------------ 2. pembimbing ganti penguji

    public function test_pembimbing_bisa_mengganti_penguji_langsung(): void
    {
        $pengujiBaru = $this->makeDosen('Penguji Baru');

        $this->actingAs($this->dosen)
            ->post(route('mahasiswa-ta.penguji', $this->ta), [
                'penguji_1_id' => $pengujiBaru->id,
                'penguji_2_id' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame($pengujiBaru->id, $this->ta->fresh()->penguji_1_id);
    }

    public function test_dosen_non_pembimbing_tidak_bisa_mengganti_penguji(): void
    {
        $pengujiBaru = $this->makeDosen('Penguji Lain');
        // Dosen yang tidak terkait sama sekali dengan program ini.
        $outsider = $this->makeDosen('Dosen Luar');

        $this->actingAs($outsider)
            ->post(route('mahasiswa-ta.penguji', $this->ta), [
                'penguji_1_id' => $pengujiBaru->id,
                'penguji_2_id' => '',
            ])
            ->assertStatus(403);

        $this->assertNotSame($pengujiBaru->id, $this->ta->fresh()->penguji_1_id);
    }

    public function test_tidak_boleh_dosen_yang_sama_jadi_pembimbing_dan_penguji(): void
    {
        $this->actingAs($this->dosen)
            ->post(route('mahasiswa-ta.penguji', $this->ta), [
                // $this->dosen adalah pembimbing 1 program ini.
                'penguji_1_id' => $this->dosen->id,
                'penguji_2_id' => '',
            ])
            ->assertStatus(422);
    }

    public function test_penguji_tidak_boleh_sama_dengan_penguji_lain(): void
    {
        [$pengujiA, $pengujiB] = [$this->makeDosen('Peng A'), $this->makeDosen('Peng B')];

        $this->actingAs($this->dosen)
            ->post(route('mahasiswa-ta.penguji', $this->ta), [
                'penguji_1_id' => $pengujiA->id,
                'penguji_2_id' => $pengujiA->id,
            ])
            ->assertStatus(422);
    }

    // ------------------------------------------- 3. dosen pembimbing buka workspace

    public function test_dosen_pembimbing_bisa_membuka_workspace_mahasiswa(): void
    {
        $this->actingAs($this->dosen)
            ->get(route('workspace.index', $this->ta))
            ->assertOk();
    }

    // ------------------------------------- 4. profil akademik status pending_approval

    public function test_profil_akademik_pending_menampilkan_menunggu_persetujuan(): void
    {
        $mhs = $this->makeMahasiswa('Mhs Pending');

        $ta = MahasiswaTa::create([
            'user_id' => $mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosen->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_PENDING_APPROVAL,
            'fase' => 'proposal',
        ]);

        $this->actingAs($mhs)
            ->get(route('profile.profil-akademik'))
            ->assertOk()
            ->assertSee('Permintaan Anda sedang menunggu persetujuan dosen pembimbing')
            ->assertDontSee('Belum ada program TA');
    }

    // ---------------------------------------------------------------- helper

    private function makeDosen(string $name): User
    {
        $u = User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)).'-'.uniqid().'@t.test',
            'password' => bcrypt('password'),
            'nidn' => 'NIDN'.substr(md5(uniqid()), 0, 10),
            'registration_status' => 'active',
        ]);
        $u->assignRole('dosen');

        return $u;
    }

    private function makeMahasiswa(string $name): User
    {
        $u = User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)).'-'.uniqid().'@t.test',
            'password' => bcrypt('password'),
            'nim' => 'NIM'.substr(md5(uniqid()), 0, 8),
            'whatsapp' => '628xxx',
            'registration_status' => 'active',
        ]);
        $u->assignRole('mahasiswa');

        return $u;
    }
}