<?php

namespace Tests\Feature;

use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Gate Lunak "Keputusan Dosen" dengan batas waktu:
 * - Selama mahasiswa pending masih di bawah batas hari → dosen TIDAK diblokir,
 *   hanya ditampilkan papan peringatan.
 * - Jika ada pending yang SUDAH lewat batas hari → dosen diarahkan ke halaman
 *   Persetujuan (diblokir dari fitur lain, kecuali keputusan & review bahan).
 * - Setelah diputuskan → akses kembali normal.
 */
class DosenPendingApprovalGateTest extends TestCase
{
    use DatabaseTransactions;

    private User $dosen;
    private MahasiswaTa $pending;
    private MahasiswaTa $active;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['dosen', 'mahasiswa'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->dosen = User::create([
            'name' => 'Dosen Gate', 'email' => 'dgate-'.uniqid().'@t.test',
            'password' => bcrypt('x'), 'registration_status' => 'active',
        ]);
        $this->dosen->assignRole('dosen');

        $mhsP = User::create([
            'name' => 'Mhs Pending', 'email' => 'mgp-'.uniqid().'@t.test',
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => 'NIM'.uniqid(),
        ]);
        $mhsP->assignRole('mahasiswa');
        $this->pending = MahasiswaTa::create([
            'user_id' => $mhsP->id, 'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosen->id, 'status_ta' => MahasiswaTa::STATUS_PENDING_APPROVAL,
        ]);

        $mhsA = User::create([
            'name' => 'Mhs Aktif', 'email' => 'mga-'.uniqid().'@t.test',
            'password' => bcrypt('x'), 'registration_status' => 'active',
            'nim' => 'NIM'.uniqid(),
        ]);
        $mhsA->assignRole('mahasiswa');
        $this->active = MahasiswaTa::create([
            'user_id' => $mhsA->id, 'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosen->id, 'status_ta' => MahasiswaTa::STATUS_AKTIF,
        ]);
    }

    private function enableGate(bool $overdue): void
    {
        config(['app.enforce_dosen_pending_approval' => true, 'app.dosen_pending_approval_deadline_days' => 4]);

        if ($overdue) {
            $this->pending->forceFill(['created_at' => now()->subDays(6)])->save();
        }
    }

    public function test_before_deadline_dosen_tidak_diblokir_dan_lihat_banner(): void
    {
        $this->enableGate(false);

        $this->actingAs($this->dosen)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('menunggu persetujuan');
    }

    public function test_overdue_dosen_di_block_ke_halaman_approval(): void
    {
        $this->enableGate(true);

        // Dashboard diblokir.
        $this->actingAs($this->dosen)->get(route('dashboard'))
            ->assertRedirect(route('approval.index'));

        // Workspace program AKTIF pun ikut diblokir saat ada pending overdue.
        $this->actingAs($this->dosen)->get(route('workspace.index', $this->active))
            ->assertRedirect(route('approval.index'));

        // Halaman approval tetap terbuka.
        $this->actingAs($this->dosen)->get(route('approval.index'))->assertOk();
    }

    public function test_overdue_tetap_bisa_review_bahan_mahasiswa_pending(): void
    {
        $this->enableGate(true);

        $this->actingAs($this->dosen)
            ->get(route('mahasiswa-ta.show', $this->pending))
            ->assertOk();
    }

    public function test_setelah_pending_diputuskan_akses_kembali_normal(): void
    {
        $this->enableGate(true);
        $this->pending->update(['status_ta' => MahasiswaTa::STATUS_AKTIF]);

        $this->actingAs($this->dosen)
            ->get(route('dashboard'))
            ->assertOk();
    }
}