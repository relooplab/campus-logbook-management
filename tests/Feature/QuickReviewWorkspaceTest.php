<?php

namespace Tests\Feature;

use App\Models\FeedbackTemplate;
use App\Models\LogbookEntry;
use App\Models\PdfComment;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QuickReviewWorkspaceTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    private function nextEntry(int $offset = 1): LogbookEntry
    {
        return LogbookEntry::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'dosen_id' => $this->dosen->id,
            'jenis' => LogbookEntry::JENIS_LOGBOOK,
            'sesi_ke' => 20 + $offset,
            'topik' => 'Topik antrean '.$offset,
            'status' => LogbookEntry::STATUS_SUBMITTED,
            'submitted_at' => now()->addMinutes($offset),
        ]);
    }

    public function test_queue_navigation_uses_authorized_order_and_shows_disabled_edges(): void
    {
        $this->entrySubmitted->update(['submitted_at' => now()->subDays(4)]);
        $second = $this->nextEntry();

        $first = $this->actingAs($this->dosen)->get(route('quick-review.index'));
        $first->assertOk()->assertSee('1 dari 2')->assertSee('4 hari')->assertSee(route('quick-review.index', ['item' => $second->id]), false)
            ->assertSee('aria-disabled="true"', false)->assertSee('Simpan &amp; Berikutnya', false)
            ->assertDontSee('4.0 hari');

        $last = $this->actingAs($this->dosen)->get(route('quick-review.index', ['item' => $second->id]));
        $last->assertOk()->assertSee('2 dari 2')->assertSee('Topik antrean 1')->assertSee('Simpan &amp; Lihat Antrean', false)
            ->assertSee(route('quick-review.index', ['item' => $this->entrySubmitted->id]), false);
    }

    public function test_query_cannot_select_entries_outside_review_queue(): void
    {
        $this->actingAs($this->dosen)->get(route('quick-review.index', ['item' => $this->entryDraft->id]))->assertNotFound();
        $this->actingAs($this->mhs)->get(route('quick-review.index', ['item' => $this->entrySubmitted->id]))->assertNotFound();
        $this->actingAs($this->dosen2)->get(route('quick-review.index', ['item' => 'not-an-id']))->assertNotFound();
    }

    public function test_decisions_advance_in_order_and_last_item_finishes(): void
    {
        $this->entrySubmitted->update(['submitted_at' => now()->subDays(2)]);
        $second = $this->nextEntry();
        $third = $this->nextEntry(2);

        $this->actingAs($this->dosen)->post(route('quick-review.approve-next', $second))->assertRedirect(route('quick-review.index', ['item' => $third->id]));
        $this->assertSame(LogbookEntry::STATUS_APPROVED, $second->fresh()->status);

        $this->actingAs($this->dosen)->post(route('quick-review.revisi-next', $third), ['feedback_dosen' => 'Pendek'])
            ->assertSessionHasErrors('feedback_dosen');
        $this->assertSame(LogbookEntry::STATUS_SUBMITTED, $third->fresh()->status);

        $feedback = 'Perbaiki pembahasan hasil pengujian secara terperinci.';
        $this->actingAs($this->dosen)->post(route('quick-review.revisi-next', $third), ['feedback_dosen' => $feedback])
            ->assertRedirect(route('quick-review.index'));
        $this->assertSame(LogbookEntry::STATUS_REVISI, $third->fresh()->status);
        $this->assertSame($feedback, $third->fresh()->feedback_dosen);

        $this->actingAs($this->dosen)->post(route('quick-review.approve-next', $this->entrySubmitted))
            ->assertRedirect(route('quick-review.index'));
        $this->actingAs($this->dosen)->get(route('quick-review.index'))->assertOk()->assertSee('Tidak ada item yang menunggu review.');
        $this->actingAs($this->dosen)->post(route('quick-review.approve-next', $second))->assertForbidden();
    }

    public function test_revision_summary_previous_comments_and_templates_use_real_data(): void
    {
        $this->entrySubmitted->update(['status' => LogbookEntry::STATUS_REVISI, 'feedback_dosen' => 'Perbaiki bab hasil terlebih dahulu.']);
        $revision = LogbookEntry::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'dosen_id' => $this->dosen->id,
            'parent_entry_id' => $this->entrySubmitted->id,
            'jenis' => LogbookEntry::JENIS_REVISI,
            'revision_round' => 1,
            'status' => LogbookEntry::STATUS_SUBMITTED,
            'submitted_at' => now()->subDay(),
            'riwayat_perbaikan' => [
                ['halaman' => 'Bab 4', 'komentar_dosen' => 'Perbaiki tabel', 'perbaikan' => 'Sudah diperbaiki', 'status' => 'Sudah'],
                ['halaman' => 'Bab 5', 'komentar_dosen' => 'Perbaiki simpulan', 'perbaikan' => 'Dalam proses', 'status' => 'Sebagian'],
            ],
        ]);
        PdfComment::create(['logbook_entry_id' => $this->entrySubmitted->id, 'user_id' => $this->dosen->id, 'file_type' => PdfComment::FILE_TYPE_DRAFT, 'page_number' => 54, 'comment' => 'Komentar halaman asli', 'resolution_status' => PdfComment::STATUS_OPEN]);
        FeedbackTemplate::create(['user_id' => $this->dosen->id, 'title' => 'Template saya', 'body' => 'Perbaiki tabel terlebih dahulu.']);
        FeedbackTemplate::create(['user_id' => $this->dosen2->id, 'title' => 'Template dosen lain', 'body' => 'Jangan terlihat.']);

        $this->actingAs($this->dosen)->get(route('quick-review.index'))->assertOk()
            ->assertSee('1 dari 2 diperbaiki')->assertSee('50%')->assertSee('Hal. 54')
            ->assertSee('Komentar halaman asli')->assertSee('Perbaiki bab hasil terlebih dahulu.')
            ->assertSee('Template saya')->assertDontSee('Template dosen lain');

        $this->actingAs($this->dosen)->postJson(route('quick-review.build-feedback', $revision))->assertOk()
            ->assertJsonPath('feedback', '1. (Sesi sebelumnya, entri #'.$this->entrySubmitted->id.', Hal. 54) Komentar halaman asli');
        $this->actingAs($this->dosen)->get(route('quick-review.index', ['item' => $revision->id]))->assertSee('Komentar halaman asli');
    }

    public function test_pdf_viewer_returns_to_authorized_quick_review_item(): void
    {
        $this->nextEntry();
        $this->actingAs($this->dosen)->get(route('logbook.pdf-viewer', ['logbook' => $this->entrySubmitted, 'quick_review' => 1]))
            ->assertOk()->assertSee(json_encode(route('quick-review.index', ['item' => $this->entrySubmitted->id])), false);
        $this->actingAs($this->mhs)->get(route('logbook.pdf-viewer', ['logbook' => $this->entrySubmitted, 'quick_review' => 1]))
            ->assertOk()->assertSee(json_encode(route('logbook.show', $this->entrySubmitted)), false);
    }
}
