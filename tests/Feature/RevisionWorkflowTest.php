<?php

namespace Tests\Feature;

use App\Models\LogbookEntry;
use App\Models\PdfComment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;

class RevisionWorkflowTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    public function test_revision_is_linked_to_parent_and_copies_review_assignment(): void
    {
        $this->entrySubmitted->update([
            'status' => LogbookEntry::STATUS_REVISI,
            'feedback_dosen' => 'Perbaiki metodologi dan jelaskan hasil pengujian dengan lebih rinci.',
            'reviewed_at' => now(),
        ]);

        $response = $this->actingAs($this->mhs)->post(route('logbook.store-revisi'), [
            'parent_entry_id' => $this->entrySubmitted->id,
            'tanggal_pengiriman' => now()->toDateString(),
            'progres_kendala' => 'Metodologi dan hasil pengujian sudah diperbaiki.',
            'riwayat_perbaikan' => [
                [
                    'halaman' => 'Bab 3',
                    'komentar_dosen' => 'Perbaiki metodologi.',
                    'perbaikan' => 'Metodologi sudah diperbaiki.',
                    'status' => 'Sudah',
                ],
            ],
            'lampiran' => UploadedFile::fake()->create('draft.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('logbook_entries', [
            'parent_entry_id' => $this->entrySubmitted->id,
            'revision_round' => 1,
            'dosen_id' => $this->dosen->id,
            'topik' => $this->entrySubmitted->topik,
            'jenis' => LogbookEntry::JENIS_REVISI,
            'status' => LogbookEntry::STATUS_REVISION_IN_PROGRESS,
        ]);
    }

    public function test_only_one_active_revision_is_allowed_per_parent(): void
    {
        $this->entrySubmitted->update(['status' => LogbookEntry::STATUS_REVISI, 'reviewed_at' => now()]);
        $payload = [
            'parent_entry_id' => $this->entrySubmitted->id,
            'tanggal_pengiriman' => now()->toDateString(),
            'progres_kendala' => 'Perbaikan sudah dikerjakan dengan ringkasan yang jelas.',
            'riwayat_perbaikan' => [
                [
                    'halaman' => 'Bab 3',
                    'komentar_dosen' => 'Perbaiki metodologi.',
                    'perbaikan' => 'Metodologi sudah diperbaiki.',
                    'status' => 'Sudah',
                ],
            ],
            'lampiran' => UploadedFile::fake()->create('draft.pdf', 100, 'application/pdf'),
        ];

        $this->actingAs($this->mhs)->post(route('logbook.store-revisi'), $payload)->assertRedirect();
        $this->actingAs($this->mhs)->post(route('logbook.store-revisi'), $payload)->assertSessionHasErrors('parent_entry_id');
    }

    public function test_saving_revision_draft_does_not_mark_comments_addressed(): void
    {
        $this->entrySubmitted->update(['status' => LogbookEntry::STATUS_REVISI, 'reviewed_at' => now()]);
        $comment = $this->entrySubmitted->comments()->create([
            'user_id' => $this->dosen->id,
            'file_type' => PdfComment::FILE_TYPE_DRAFT,
            'page_number' => 1,
            'comment' => 'Tambahkan penjelasan pada bagian ini.',
            'resolution_status' => PdfComment::STATUS_OPEN,
            'is_resolved' => false,
        ]);

        $this->actingAs($this->mhs)->post(route('logbook.store-revisi'), [
            'parent_entry_id' => $this->entrySubmitted->id,
            'addressed_comment_ids' => [$comment->id],
            'tanggal_pengiriman' => now()->toDateString(),
            'progres_kendala' => 'Perbaikan sedang disiapkan dalam draft.',
            'riwayat_perbaikan' => [
                [
                    'halaman' => 'Bab 3',
                    'komentar_dosen' => 'Tambahkan penjelasan pada bagian ini.',
                    'perbaikan' => 'Penjelasan sedang disiapkan.',
                    'status' => 'Sebagian',
                ],
            ],
            'lampiran' => UploadedFile::fake()->create('draft.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseHas('pdf_comments', [
            'id' => $comment->id,
            'resolution_status' => PdfComment::STATUS_OPEN,
        ]);
    }

    public function test_comment_moves_from_open_to_addressed_then_resolved(): void
    {
        $comment = $this->entrySubmitted->comments()->create([
            'user_id' => $this->dosen->id,
            'file_type' => PdfComment::FILE_TYPE_DRAFT,
            'page_number' => 1,
            'comment' => 'Jelaskan sumber data pada halaman ini.',
            'resolution_status' => PdfComment::STATUS_OPEN,
            'is_resolved' => false,
        ]);

        $this->actingAs($this->mhs)
            ->postJson(route('pdf-comments.resolve', $comment))
            ->assertJsonPath('resolution_status', PdfComment::STATUS_ADDRESSED);

        $this->actingAs($this->dosen)
            ->postJson(route('pdf-comments.resolve', $comment))
            ->assertJsonPath('resolution_status', PdfComment::STATUS_RESOLVED);
    }

    public function test_create_revisi_prefills_rows_from_parent_comments(): void
    {
        $this->entrySubmitted->update([
            'status' => LogbookEntry::STATUS_REVISI,
            'reviewed_at' => now(),
        ]);

        $this->entrySubmitted->comments()->create([
            'user_id' => $this->dosen->id,
            'file_type' => PdfComment::FILE_TYPE_DRAFT,
            'page_number' => 3,
            'comment' => 'Perbaiki diagram pada bab ini.',
            'resolution_status' => PdfComment::STATUS_OPEN,
            'is_resolved' => false,
        ]);

        $this->actingAs($this->mhs)
            ->get(route('logbook.create-revisi', ['parent_entry_id' => $this->entrySubmitted->id]))
            ->assertOk()
            ->assertSee('Hal. 3')
            ->assertSee('Perbaiki diagram pada bab ini.');
    }

    public function test_submit_revisi_without_pesan_does_not_500(): void
    {
        $this->entrySubmitted->update([
            'status' => LogbookEntry::STATUS_REVISI,
            'reviewed_at' => now(),
        ]);

        // "Pesan untuk Dosen" (progres_kendala) opsional — dikosongkan.
        $response = $this->actingAs($this->mhs)->post(route('logbook.store-revisi'), [
            'parent_entry_id' => $this->entrySubmitted->id,
            'tanggal_pengiriman' => now()->toDateString(),
            'progres_kendala' => '',
            'riwayat_perbaikan' => [
                ['halaman' => 'Hal. 3', 'komentar_dosen' => 'Perbaiki diagram.', 'perbaikan' => 'Sudah.', 'status' => 'Sudah'],
            ],
            'lampiran' => UploadedFile::fake()->create('draft.pdf', 100, 'application/pdf'),
            'submit' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('logbook_entries', [
            'parent_entry_id' => $this->entrySubmitted->id,
            'jenis' => LogbookEntry::JENIS_REVISI,
            'progres_kendala' => null,
        ]);
    }

    public function test_update_revisi_without_pesan_does_not_500(): void
    {
        $this->entrySubmitted->update([
            'status' => LogbookEntry::STATUS_REVISI,
            'reviewed_at' => now(),
        ]);

        $draft = LogbookEntry::create([
            'mahasiswa_ta_id' => $this->entrySubmitted->mahasiswa_ta_id,
            'parent_entry_id' => $this->entrySubmitted->id,
            'revision_round' => 1,
            'sesi_ke' => null, // revisi: sesi tidak dipakai (null)
            'jenis' => LogbookEntry::JENIS_REVISI,
            'dosen_id' => $this->dosen->id,
            'topik' => $this->entrySubmitted->topik,
            'progres_kendala' => 'Pesan awal',
            'tanggal_pengiriman' => now()->toDateString(),
            'status' => LogbookEntry::STATUS_REVISION_IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->mhs)->put(route('logbook.update', $draft), [
            'tanggal_pengiriman' => now()->toDateString(),
            'progres_kendala' => '',
            'riwayat_perbaikan' => [
                ['halaman' => 'Hal. 3', 'komentar_dosen' => 'Perbaiki diagram.', 'perbaikan' => 'Sudah.', 'status' => 'Sudah'],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('logbook_entries', [
            'id' => $draft->id,
            'progres_kendala' => null,
        ]);
    }

    public function test_revision_feedback_must_be_meaningful(): void
    {
        $this->actingAs($this->dosen)
            ->post(route('logbook.request-revisi', $this->entrySubmitted), ['feedback_dosen' => 'Perbaiki.'])
            ->assertSessionHasErrors('feedback_dosen');
    }
}
