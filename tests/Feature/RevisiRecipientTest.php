<?php

namespace Tests\Feature;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Alur "kirim perbaikan kepada dosen penguji":
 *  - Mahasiswa memilih penerima revisi (pembimbing ATAU dosen penguji).
 *  - Penerima menjadi reviewer entri (bisa Setujui / Minta Revisi).
 *  - Pembimbing tetap menerima notifikasi (CC) & tetap bisa mereview.
 */
class RevisiRecipientTest extends TestCase
{
    use DatabaseTransactions;

    private User $mahasiswa;

    private User $pembimbing;

    private User $penguji;

    private User $dosenLain;

    private MahasiswaTa $ta;

    private LogbookEntry $parent;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['mahasiswa', 'dosen'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->mahasiswa = $this->makeUser('Mhs Revisi', 'mahasiswa', ['nim' => 'NIM'.substr(md5(uniqid()), 0, 8)]);
        $this->pembimbing = $this->makeUser('Pembimbing Satu', 'dosen', ['nidn' => 'NIDN'.substr(md5(uniqid()), 0, 10)]);
        $this->penguji = $this->makeUser('Penguji Satu', 'dosen', ['nidn' => 'NIDN'.substr(md5(uniqid()), 0, 10)]);
        $this->dosenLain = $this->makeUser('Dosen Lain', 'dosen', ['nidn' => 'NIDN'.substr(md5(uniqid()), 0, 10)]);

        $this->ta = MahasiswaTa::create([
            'user_id' => $this->mahasiswa->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->pembimbing->id,
            'penguji_1_id' => $this->penguji->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
            'fase' => 'proposal',
        ]);

        $this->parent = LogbookEntry::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'jenis' => LogbookEntry::JENIS_LOGBOOK,
            'sesi_ke' => 1,
            'dosen_id' => $this->pembimbing->id,
            'topik' => 'Bimbingan 1',
            'status' => LogbookEntry::STATUS_REVISI,
            'reviewed_at' => now(),
        ]);
    }

    private function makeUser(string $name, string $role, array $extra = []): User
    {
        $user = User::create(array_merge([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid().'@t.test',
            'password' => bcrypt('password'),
            'whatsapp' => '6281234567890',
            'registration_status' => 'active',
        ], $extra));

        $user->assignRole($role);

        return $user;
    }

    private function revisiPayload(int $recipientId, bool $submit = true): array
    {
        return [
            'parent_entry_id' => $this->parent->id,
            'addressed_dosen_id' => $recipientId,
            'submit' => $submit ? 1 : null,
            'tanggal_pengiriman' => now()->toDateString(),
            'progres_kendala' => 'Perbaikan sudah dikerjakan sesuai komentar dosen.',
            'riwayat_perbaikan' => [
                [
                    'halaman' => 'Bab 3',
                    'komentar_dosen' => 'Perbaiki metodologi.',
                    'perbaikan' => 'Metodologi sudah diperbaiki.',
                    'status' => LogbookEntry::PERBAIKAN_SUDAH,
                ],
            ],
            'lampiran' => UploadedFile::fake()->create('revisi.pdf', 100, 'application/pdf'),
        ];
    }

    private function entryRevisiTerakhir(): LogbookEntry
    {
        return LogbookEntry::where('parent_entry_id', $this->parent->id)->firstOrFail();
    }

    public function test_label_peran_dosen_mengenali_pembimbing_dan_penguji(): void
    {
        $this->assertSame('Pembimbing 1', $this->ta->dosenRoleLabel($this->pembimbing));
        $this->assertSame('Penguji 1', $this->ta->dosenRoleLabel($this->penguji));
        $this->assertNull($this->ta->dosenRoleLabel($this->dosenLain));

        $options = $this->ta->dosenRecipientOptions();
        $this->assertArrayHasKey($this->pembimbing->id, $options);
        $this->assertArrayHasKey($this->penguji->id, $options);
        $this->assertStringContainsString('Penguji 1 — Penguji Satu', $options[$this->penguji->id]);
    }

    public function test_dosen_ganda_peran_hanya_muncul_sekali_dengan_label_gabungan(): void
    {
        // Mode individual: satu dosen menjadi pembimbing 1 sekaligus penguji 1.
        $this->ta->update(['penguji_1_id' => $this->pembimbing->id]);

        $ta = $this->ta->fresh();
        $this->assertSame('Pembimbing 1 & Penguji 1', $ta->dosenRoleLabel($this->pembimbing));

        $options = $ta->dosenRecipientOptions();
        $dosenIds = array_keys($options);
        $this->assertSame([$this->pembimbing->id], array_values(array_filter($dosenIds, fn ($id) => $id === $this->pembimbing->id)));
        $this->assertContains('Pembimbing 1 & Penguji 1 — Pembimbing Satu', $options);
        $this->assertCount(1, $options);
    }

    public function test_mahasiswa_dapat_mengirim_revisi_kepada_dosen_penguji(): void
    {
        $this->actingAs($this->mahasiswa)
            ->post(route('logbook.store-revisi'), $this->revisiPayload($this->penguji->id))
            ->assertRedirect();

        $entry = $this->entryRevisiTerakhir();
        $this->assertSame(LogbookEntry::JENIS_REVISI, $entry->jenis);
        $this->assertSame($this->penguji->id, $entry->dosen_id);
        $this->assertSame(LogbookEntry::STATUS_SUBMITTED, $entry->status);
        $this->assertSame($this->penguji->id, $entry->reviewDosen()?->id);

        // Penerima (penguji) dan pembimbing (CC) sama-sama diberi tahu.
        $this->assertSame(1, $this->penguji->fresh()->notifications()->count());
        $this->assertSame(1, $this->pembimbing->fresh()->notifications()->count());
    }

    public function test_revisi_tanpa_penerima_tetap_ke_pembimbing(): void
    {
        $payload = $this->revisiPayload($this->pembimbing->id);
        unset($payload['addressed_dosen_id']);

        $this->actingAs($this->mahasiswa)
            ->post(route('logbook.store-revisi'), $payload)
            ->assertRedirect();

        $this->assertSame($this->pembimbing->id, $this->entryRevisiTerakhir()->dosen_id);
    }

    public function test_penerima_di_luar_program_ditolak(): void
    {
        $this->actingAs($this->mahasiswa)
            ->post(route('logbook.store-revisi'), $this->revisiPayload($this->dosenLain->id))
            ->assertSessionHasErrors('addressed_dosen_id');
    }

    public function test_dosen_penguji_dapat_membuka_dan_menyetujui_revisi(): void
    {
        $this->actingAs($this->mahasiswa)
            ->post(route('logbook.store-revisi'), $this->revisiPayload($this->penguji->id))
            ->assertRedirect();

        $entry = $this->entryRevisiTerakhir();

        // Penguji (penerima) = reviewer penuh.
        $this->actingAs($this->penguji)->get(route('logbook.show', $entry))->assertOk();
        $this->actingAs($this->penguji)->get(route('logbook.pdf-viewer', $entry))->assertOk();
        $this->actingAs($this->penguji)->post(route('logbook.approve', $entry))->assertRedirect();

        $this->assertSame(LogbookEntry::STATUS_APPROVED, $entry->fresh()->status);
    }

    public function test_dosen_penguji_dapat_meminta_revisi(): void
    {
        $this->actingAs($this->mahasiswa)
            ->post(route('logbook.store-revisi'), $this->revisiPayload($this->penguji->id))
            ->assertRedirect();

        $entry = $this->entryRevisiTerakhir();

        $this->actingAs($this->penguji)
            ->post(route('logbook.request-revisi', $entry), [
                'feedback_dosen' => 'Bagian analisis masih perlu diperbaiki lagi dengan detail.',
            ])
            ->assertRedirect();

        $this->assertSame(LogbookEntry::STATUS_REVISI, $entry->fresh()->status);
    }

    public function test_dosen_penguji_luar_program_tidak_dapat_menyetujui(): void
    {
        $this->actingAs($this->mahasiswa)
            ->post(route('logbook.store-revisi'), $this->revisiPayload($this->penguji->id))
            ->assertRedirect();

        $entry = $this->entryRevisiTerakhir();

        $this->actingAs($this->dosenLain)->post(route('logbook.approve', $entry))->assertForbidden();
    }

    public function test_pembimbing_tetap_dapat_mereview_revisi_untuk_penguji(): void
    {
        $this->actingAs($this->mahasiswa)
            ->post(route('logbook.store-revisi'), $this->revisiPayload($this->penguji->id))
            ->assertRedirect();

        $entry = $this->entryRevisiTerakhir();

        $this->assertTrue($this->pembimbing->can('review', $entry));
        $this->actingAs($this->pembimbing)->post(route('logbook.approve', $entry))->assertRedirect();
        $this->assertSame(LogbookEntry::STATUS_APPROVED, $entry->fresh()->status);
    }

    public function test_penguji_penerima_muncul_di_antrean_review(): void
    {
        $this->actingAs($this->mahasiswa)
            ->post(route('logbook.store-revisi'), $this->revisiPayload($this->penguji->id))
            ->assertRedirect();

        $entry = $this->entryRevisiTerakhir();

        $this->assertTrue($this->penguji->can('review', $entry));
        $this->actingAs($this->penguji)->get(route('quick-review.index'))->assertOk();
    }

    public function test_dosen_peran_ganda_hanya_muncul_sekali_dengan_label_gabungan(): void
    {
        $this->ta->update(['pembimbing_2_id' => $this->penguji->id]);

        $options = $this->ta->fresh()->dosenRecipientOptions();

        $this->assertCount(2, $options);
        $this->assertSame(
            'Pembimbing 2 & Penguji 1 — Penguji Satu',
            $options[$this->penguji->id]
        );
        $this->assertSame(
            'Pembimbing 1 — Pembimbing Satu',
            $options[$this->pembimbing->id]
        );
    }

    public function test_form_revisi_menampilkan_pilihan_penguji(): void
    {
        $response = $this->actingAs($this->mahasiswa)->get(route('logbook.create-revisi'));

        $response->assertOk();
        $response->assertSee('Kirim kepada (penerima perbaikan)');
        $response->assertSee('Penguji 1 — Penguji Satu');
        $response->assertSee('Pembimbing 1 — Pembimbing Satu');
    }
}
