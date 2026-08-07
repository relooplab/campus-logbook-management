<?php

namespace Tests\Feature\Backup;

use App\Models\Conversation;
use App\Models\Institution;
use App\Models\MahasiswaTa;
use App\Models\Sidang;
use App\Models\University;
use App\Models\User;
use App\Services\Backup\InstitutionClosureResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Test paling kritis di fitur backup selektif: closure institusi harus
 * menarik masuk user lintas-institusi yang direferensikan (pembimbing/
 * penguji/reviewer) — kalau tidak, restore parsial bisa membuat FK orphan.
 */
class InstitutionClosureResolverTest extends TestCase
{
    use DatabaseTransactions;

    private InstitutionClosureResolver $resolver;

    private Institution $institutionA;

    private Institution $institutionB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new InstitutionClosureResolver();

        $this->institutionA = Institution::create([
            'app_name' => 'Closure Test A',
            'institution_name' => 'Institusi A',
            'email' => 'a@closure-test.com',
        ]);

        $this->institutionB = Institution::create([
            'app_name' => 'Closure Test B',
            'institution_name' => 'Institusi B',
            'email' => 'b@closure-test.com',
        ]);
    }

    public function test_no_institution_filter_returns_wildcard_for_all_tables(): void
    {
        $result = $this->resolver->resolve(['users', 'mahasiswa_ta', 'universities'], [], false);

        $this->assertSame('*', $result['scope']['users']);
        $this->assertSame('*', $result['scope']['mahasiswa_ta']);
        $this->assertSame('*', $result['scope']['universities']);
        $this->assertSame([], $result['closure_expansions']);
    }

    public function test_cross_institution_pembimbing_is_pulled_into_users_scope(): void
    {
        $mahasiswaUser = User::create([
            'name' => 'Mahasiswa A', 'email' => 'mhs-a@closure-test.com', 'password' => bcrypt('x'),
            'institution_id' => $this->institutionA->id,
        ]);
        $pembimbingLuar = User::create([
            'name' => 'Dosen B', 'email' => 'dosen-b@closure-test.com', 'password' => bcrypt('x'),
            'institution_id' => $this->institutionB->id,
        ]);

        $ta = MahasiswaTa::create([
            'institution_id' => $this->institutionA->id,
            'user_id' => $mahasiswaUser->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $pembimbingLuar->id,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
        ]);

        $result = $this->resolver->resolve(['users', 'mahasiswa_ta'], [$this->institutionA->id], false);

        $this->assertContains($mahasiswaUser->id, $result['scope']['users']);
        $this->assertContains($pembimbingLuar->id, $result['scope']['users']);
        $this->assertContains($ta->id, $result['scope']['mahasiswa_ta']);

        $expansionUserIds = array_column($result['closure_expansions'], 'user_id');
        $this->assertContains($pembimbingLuar->id, $expansionUserIds);

        $reasonForPembimbing = collect($result['closure_expansions'])
            ->firstWhere('user_id', $pembimbingLuar->id)['reason'] ?? null;
        $this->assertSame('mahasiswa_ta_role', $reasonForPembimbing);
    }

    public function test_cross_institution_sidang_penguji_is_pulled_in(): void
    {
        $mahasiswaUser = User::create([
            'name' => 'Mahasiswa A2', 'email' => 'mhs-a2@closure-test.com', 'password' => bcrypt('x'),
            'institution_id' => $this->institutionA->id,
        ]);
        $pengujiLuar = User::create([
            'name' => 'Penguji B', 'email' => 'penguji-b@closure-test.com', 'password' => bcrypt('x'),
            'institution_id' => $this->institutionB->id,
        ]);

        $ta = MahasiswaTa::create([
            'institution_id' => $this->institutionA->id,
            'user_id' => $mahasiswaUser->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
        ]);

        $sidang = Sidang::create([
            'mahasiswa_ta_id' => $ta->id,
            'penguji_id' => $pengujiLuar->id,
            'jenis' => Sidang::JENIS_SIDANG,
            'tanggal' => now(),
        ]);

        $result = $this->resolver->resolve(['users', 'mahasiswa_ta', 'sidangs'], [$this->institutionA->id], false);

        $this->assertContains($pengujiLuar->id, $result['scope']['users']);
        $this->assertContains($sidang->id, $result['scope']['sidangs']);

        $reason = collect($result['closure_expansions'])->firstWhere('user_id', $pengujiLuar->id)['reason'] ?? null;
        $this->assertSame('sidang_penguji', $reason);
    }

    public function test_conversation_only_included_when_both_participants_in_scope(): void
    {
        $userInScope1 = User::create(['name' => 'In Scope 1', 'email' => 'in1@closure-test.com', 'password' => bcrypt('x'), 'institution_id' => $this->institutionA->id]);
        $userInScope2 = User::create(['name' => 'In Scope 2', 'email' => 'in2@closure-test.com', 'password' => bcrypt('x'), 'institution_id' => $this->institutionA->id]);
        $userOutsideScope = User::create(['name' => 'Outside', 'email' => 'outside@closure-test.com', 'password' => bcrypt('x'), 'institution_id' => $this->institutionB->id]);

        // Hitung baseline dulu (tabel `conversations` bisa sudah berisi baris
        // dari fixture test class lain yang tidak memakai transaction rollback,
        // mis. AuditSmokeTest — jadi assert harus relatif, bukan hitungan mutlak).
        $baseline = $this->resolver->resolve(['users', 'mahasiswa_ta', 'conversations'], [$this->institutionA->id], false);
        $baselineSkipped = $baseline['skipped_conversations_outside_scope'];

        $convoInside = Conversation::create([
            'user_one_id' => min($userInScope1->id, $userInScope2->id),
            'user_two_id' => max($userInScope1->id, $userInScope2->id),
        ]);
        $convoOutside = Conversation::create([
            'user_one_id' => min($userInScope1->id, $userOutsideScope->id),
            'user_two_id' => max($userInScope1->id, $userOutsideScope->id),
        ]);

        $result = $this->resolver->resolve(['users', 'mahasiswa_ta', 'conversations'], [$this->institutionA->id], false);

        $this->assertContains($convoInside->id, $result['scope']['conversations']);
        $this->assertNotContains($convoOutside->id, $result['scope']['conversations']);
        $this->assertSame($baselineSkipped + 1, $result['skipped_conversations_outside_scope']);
    }

    public function test_include_individual_pulls_in_null_institution_users(): void
    {
        $individualUser = User::create([
            'name' => 'Individual', 'email' => 'individual@closure-test.com', 'password' => bcrypt('x'),
            'institution_id' => null,
        ]);

        $withoutIndividual = $this->resolver->resolve(['users'], [$this->institutionA->id], false);
        $this->assertNotContains($individualUser->id, $withoutIndividual['scope']['users']);

        $withIndividual = $this->resolver->resolve(['users'], [$this->institutionA->id], true);
        $this->assertContains($individualUser->id, $withIndividual['scope']['users']);
    }

    public function test_catalog_tables_are_always_wildcard_regardless_of_filter(): void
    {
        University::create(['name' => 'Universitas Closure Test']);

        $result = $this->resolver->resolve(['universities'], [$this->institutionA->id], false);

        $this->assertSame('*', $result['scope']['universities']);
    }
}
