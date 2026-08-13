<?php

namespace Tests\Feature;

use App\Models\MahasiswaTa;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Test untuk Feature::storageDisplayMetadata() — metadata kuota untuk tampilan
 * dashboard admin agar tidak menyesatkan:
 *  - Dosen/admin    : ikut paket/pool (angka = storageLimitMb).
 *  - Mahasiswa      : 100 MB sementara bila ada program pending/ditolak,
 *                     "ikut dosen pembimbing" bila semua program sudah disetujui.
 */
class StorageDisplayMetadataTest extends TestCase
{
    use DatabaseTransactions;

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    private function mahasiswa(string $email, string $nim): User
    {
        $this->role('mahasiswa');
        $u = User::create([
            'name' => 'Mhs '.$nim,
            'email' => $email,
            'password' => bcrypt('x'),
            'registration_status' => 'active',
            'nim' => $nim,
            'whatsapp' => '08123456789',
        ]);
        $u->assignRole('mahasiswa');

        return $u;
    }

    private function dosen(): User
    {
        $this->role('dosen');
        $u = User::create([
            'name' => 'Dosen Pembimbing',
            'email' => 'dospem-'.uniqid().'@t.test',
            'password' => bcrypt('x'),
            'registration_status' => 'active',
            'nidn' => 'NIDN-'.substr(md5(uniqid()), 0, 10),
        ]);
        $u->assignRole('dosen');

        return $u;
    }

    public function test_dosen_mengikuti_storage_limit(): void
    {
        $d = $this->dosen();

        $meta = Feature::storageDisplayMetadata($d);

        $this->assertSame(Feature::storageLimitMb($d), $meta['mb']);
        $this->assertSame('ikut paket/pool', $meta['note']);
    }

    public function test_mahasiswa_dengan_program_pending_mendapat_100_mb_sementara(): void
    {
        $mhs = $this->mahasiswa('pending-meta@t.test', 'NIM-PEND-1');
        $d = $this->dosen();
        MahasiswaTa::create([
            'user_id' => $mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $d->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_PENDING_APPROVAL,
            'fase' => 'proposal',
        ]);

        $meta = Feature::storageDisplayMetadata($mhs);

        $this->assertSame(Feature::pendingStudentStorageLimitMb(), $meta['mb']);
        $this->assertSame(100, $meta['mb']);
        $this->assertStringContainsString('pending', $meta['note']);
    }

    public function test_mahasiswa_dengan_program_ditolak_mendapat_100_mb_sementara(): void
    {
        $mhs = $this->mahasiswa('ditolak-meta@t.test', 'NIM-DTLK-1');
        $d = $this->dosen();
        MahasiswaTa::create([
            'user_id' => $mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $d->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_DITOLAK,
            'fase' => 'proposal',
        ]);

        $meta = Feature::storageDisplayMetadata($mhs);

        $this->assertSame(100, $meta['mb']);
    }

    public function test_mahasiswa_tanpa_program_ikut_dosen_pembimbing(): void
    {
        $mhs = $this->mahasiswa('no-program-meta@t.test', 'NIM-NONE-1');

        $meta = Feature::storageDisplayMetadata($mhs);

        $this->assertNull($meta['mb']);
        $this->assertSame('ikut dosen pembimbing', $meta['note']);
    }

    public function test_mahasiswa_dengan_program_disetujui_ikut_dosen_pembimbing(): void
    {
        $mhs = $this->mahasiswa('aktif-meta@t.test', 'NIM-AKTIF-1');
        $d = $this->dosen();
        MahasiswaTa::create([
            'user_id' => $mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $d->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
            'fase' => 'proposal',
        ]);

        $meta = Feature::storageDisplayMetadata($mhs);

        $this->assertNull($meta['mb']);
        $this->assertSame('ikut dosen pembimbing', $meta['note']);
    }
}