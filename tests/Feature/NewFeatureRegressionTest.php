<?php

namespace Tests\Feature;

use App\Models\ActionItem;
use App\Models\FinalizationApproval;
use App\Models\Sidang;
use App\Models\ThesisFinalization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Regression untuk empat fitur baru:
 *  1. Dosen bisa membuat action item dari feedback (reviewer).
 *  2. Mahasiswa bisa melihat hasil sidangnya sendiri di dashboard.
 *  3. Penolakan item finalisasi wajib memuat alasan (disimpan + ditampilkan).
 *  4. Indikator "sudah dilihat dosen" untuk mahasiswa (review_opened_at).
 */
class NewFeatureRegressionTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    // ---------------------------------------------------------------- 1. Action item dari dosen

    public function test_dosen_pembimbing_bisa_membuat_action_item(): void
    {
        $this->actingAs($this->dosen)
            ->postJson(route('action-items.store', $this->entrySubmitted), ['text' => 'Checklist dari feedback dosen'])
            ->assertStatus(201);

        $this->assertTrue(
            ActionItem::where('logbook_entry_id', $this->entrySubmitted->id)
                ->where('text', 'Checklist dari feedback dosen')
                ->exists()
        );
    }

    public function test_dosen_non_pembimbing_tidak_bisa_membuat_action_item(): void
    {
        $other = User::firstOrCreate(
            ['email' => 'audit-dosen-x@test.com'],
            ['name' => 'Audit Dosen X', 'password' => bcrypt('password')]
        );
        if (!$other->hasRole('dosen')) $other->assignRole('dosen');

        $this->actingAs($other)
            ->postJson(route('action-items.store', $this->entrySubmitted), ['text' => 'x'])
            ->assertStatus(403);
    }

    public function test_toggle_selesai_tetap_khusus_mahasiswa(): void
    {
        // entryRevisi ber-status draft (editable), jadi pemilik boleh toggle.
        $item = ActionItem::create(['logbook_entry_id' => $this->entryRevisi->id, 'text' => 'Item x']);

        $this->actingAs($this->dosen)
            ->postJson(route('action-items.toggle', [$this->entryRevisi, $item]))
            ->assertStatus(403);
        $this->assertFalse($item->fresh()->is_done);

        $this->actingAs($this->mhs)
            ->postJson(route('action-items.toggle', [$this->entryRevisi, $item]))
            ->assertOk();
        $this->assertTrue($item->fresh()->is_done);
    }

    // ---------------------------------------------------------------- 2. Hasil sidang untuk mahasiswa

    public function test_dashboard_mahasiswa_menampilkan_hasil_sidang(): void
    {
        Sidang::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'mahasiswa_name' => $this->mhs->name,
            'penguji_id' => $this->dosen2->id,
            'jenis' => Sidang::JENIS_SIDANG,
            'tanggal' => now(),
            'hasil' => Sidang::HASIL_LULUS,
        ]);

        $this->actingAs($this->mhs)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Hasil Sidang')
            ->assertSee('Lulus');
    }

    // ---------------------------------------------------------------- 3. Alasan wajib saat tolak finalisasi

    public function test_tolak_finalisasi_tanpa_alasan_gagal(): void
    {
        $f = ThesisFinalization::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'abstrak' => 'Abstrak',
            'abstrak_status' => 'submitted',
        ]);

        $this->actingAs($this->dosen)
            ->from(route('finalization.review'))
            ->post(route('finalization.reject', [$f, 'abstrak']), ['alasan' => ''])
            ->assertSessionHasErrors('alasan');
    }

    public function test_tolak_finalisasi_dengan_alasan_tersimpan(): void
    {
        $f = ThesisFinalization::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'abstrak' => 'Abstrak',
            'abstrak_status' => 'submitted',
        ]);
        $alasan = 'Abstrak terlalu singkat dan kurang padat isinya.';

        $this->actingAs($this->dosen)
            ->post(route('finalization.reject', [$f, 'abstrak']), ['alasan' => $alasan])
            ->assertRedirect();

        $this->assertDatabaseHas('finalization_approvals', [
            'finalization_id' => $f->id,
            'item' => 'abstrak',
            'pembimbing_id' => $this->dosen->id,
            'status' => 'rejected',
            'alasan' => $alasan,
        ]);
        $this->assertSame('rejected', $f->fresh()->abstrak_status);
    }

    public function test_mahasiswa_melihat_alasan_penolakan_di_finalisasi(): void
    {
        $f = ThesisFinalization::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'abstrak' => 'Abstrak',
            'abstrak_status' => 'rejected',
        ]);
        FinalizationApproval::create([
            'finalization_id' => $f->id,
            'item' => 'abstrak',
            'pembimbing_id' => $this->dosen->id,
            'status' => 'rejected',
            'alasan' => 'Format abstrak salah.',
        ]);

        $this->actingAs($this->mhs)
            ->get(route('finalization.index', $this->ta))
            ->assertOk()
            ->assertSee('Format abstrak salah.');
    }

    // ---------------------------------------------------------------- 4. Indikator "sudah dilihat dosen"

    public function test_mahasiswa_melihat_indikator_sudah_dilihat_dosen(): void
    {
        $this->entrySubmitted->update(['review_opened_at' => now()]);

        $this->actingAs($this->mhs)
            ->get(route('logbook.show', $this->entrySubmitted))
            ->assertOk()
            ->assertSee('Sudah dilihat dosen');
    }

    public function test_mahasiswa_melihat_indikator_belum_dilihat_dosen(): void
    {
        $this->entrySubmitted->update(['review_opened_at' => null]);

        $this->actingAs($this->mhs)
            ->get(route('logbook.show', $this->entrySubmitted))
            ->assertOk()
            ->assertSee('Belum dilihat dosen');
    }
}
