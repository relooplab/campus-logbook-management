<?php

namespace Tests\Feature\Backup;

use App\Services\Backup\BackupIntegrityChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Test paling safety-critical di jalur restore parsial: kalau closure saat
 * backup vs saat restore berbeda (mis. user yang direferensikan sudah
 * dihapus sejak backup diambil), orphan HARUS terdeteksi & dilaporkan, tidak
 * boleh diam-diam dibiarkan.
 *
 * Sengaja TIDAK memakai trait DatabaseTransactions: sqlite menolak mengubah
 * PRAGMA foreign_keys di dalam transaksi terbuka (no-op, bukan error), jadi
 * kita perlu transaksi tidak aktif supaya bisa mematikan FK constraint untuk
 * mensimulasikan orphan (skenario ini di dunia nyata muncul karena scoped
 * restore sengaja jalan dengan FOREIGN_KEY_CHECKS=0). Sebagai gantinya,
 * pembersihan data dilakukan manual di tearDown().
 */
class BackupIntegrityCheckerTest extends TestCase
{
    private BackupIntegrityChecker $checker;

    private array $mahasiswaTaIds = [];

    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new BackupIntegrityChecker();
    }

    protected function tearDown(): void
    {
        Schema::disableForeignKeyConstraints();
        if ($this->mahasiswaTaIds !== []) {
            DB::table('mahasiswa_ta')->whereIn('id', $this->mahasiswaTaIds)->delete();
        }
        if ($this->userIds !== []) {
            DB::table('users')->whereIn('id', $this->userIds)->delete();
        }
        Schema::enableForeignKeyConstraints();

        parent::tearDown();
    }

    private function makeUser(string $email): int
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Integrity Test User',
            'email' => $email,
            'password' => bcrypt('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->userIds[] = $id;

        return $id;
    }

    private function makeMahasiswaTa(int $userId, ?int $pembimbing1Id = null): int
    {
        $id = DB::table('mahasiswa_ta')->insertGetId([
            'user_id' => $userId,
            'pembimbing_1_id' => $pembimbing1Id,
            'jenis' => 'ta',
            'status_ta' => 'aktif',
            'target_sesi' => 16,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->mahasiswaTaIds[] = $id;

        return $id;
    }

    public function test_clean_data_reports_no_findings(): void
    {
        $mahasiswaUserId = $this->makeUser('clean-mhs@integrity-test.com');
        $dosenId = $this->makeUser('clean-dosen@integrity-test.com');
        $this->makeMahasiswaTa($mahasiswaUserId, $dosenId);

        $findings = $this->checker->verify(['mahasiswa_ta', 'users']);

        $this->assertSame([], $findings);
    }

    public function test_orphan_pembimbing_reference_is_detected(): void
    {
        $mahasiswaUserId = $this->makeUser('orphan-mhs@integrity-test.com');
        $dosenId = $this->makeUser('orphan-dosen@integrity-test.com');
        $taId = $this->makeMahasiswaTa($mahasiswaUserId, $dosenId);

        // Simulasikan orphan: dosen yang direferensikan "sudah tidak ada"
        // (mis. terhapus sejak backup diambil).
        Schema::disableForeignKeyConstraints();
        DB::table('mahasiswa_ta')->where('id', $taId)->update(['pembimbing_1_id' => 999999]);
        Schema::enableForeignKeyConstraints();

        $findings = $this->checker->verify(['mahasiswa_ta', 'users']);

        $pembimbingFinding = collect($findings)->firstWhere('column', 'pembimbing_1_id');
        $this->assertNotNull($pembimbingFinding);
        $this->assertSame('mahasiswa_ta', $pembimbingFinding['table']);
        $this->assertSame('users', $pembimbingFinding['references']);
        $this->assertGreaterThanOrEqual(1, $pembimbingFinding['orphan_count']);
        $this->assertContains($taId, $pembimbingFinding['sample_ids']);
    }

    public function test_tables_not_in_restored_list_are_skipped(): void
    {
        $mahasiswaUserId = $this->makeUser('skip-mhs@integrity-test.com');
        $taId = $this->makeMahasiswaTa($mahasiswaUserId);

        Schema::disableForeignKeyConstraints();
        DB::table('mahasiswa_ta')->where('id', $taId)->update(['pembimbing_1_id' => 999999]);
        Schema::enableForeignKeyConstraints();

        // 'mahasiswa_ta' sengaja TIDAK dimasukkan ke daftar tabel yang direstore.
        $findings = $this->checker->verify(['users']);

        $this->assertSame([], $findings);
    }
}
