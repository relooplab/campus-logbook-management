<?php

namespace Tests\Feature;

use App\Models\LogbookEntry;
use App\Models\SeminarSubmission;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class DetailWorkspaceTest extends AuditSmokeTest
{
    use DatabaseTransactions;

    public function test_reviewer_sees_revision_workspace_and_existing_actions(): void
    {
        $this->entryRevisi->update([
            'dosen_id' => $this->dosen->id,
            'status' => LogbookEntry::STATUS_SUBMITTED,
            'lampiran_path' => 'lampiran/revisi.pdf',
            'riwayat_perbaikan' => [
                ['halaman' => 'IV-1', 'komentar_dosen' => 'Perbaiki data tabel.', 'perbaikan' => 'Data tabel diperbaiki.', 'status' => 'Sudah'],
                ['halaman' => 'IV-2', 'komentar_dosen' => 'Periksa gambar.', 'perbaikan' => 'Gambar diperiksa.', 'status' => 'Belum'],
            ],
        ]);

        $response = $this->actingAs($this->dosen)->get(route('logbook.show', $this->entryRevisi));

        $response->assertOk()
            ->assertSee('Catatan Perbaikan')
            ->assertSee('1 dari 2 diperbaiki')
            ->assertSee('Perbaikan yang Dilakukan Mahasiswa')
            ->assertSee('revision-expand-all')
            ->assertSee('action-item-add-toggle')
            ->assertSee('review-decision-form')
            ->assertSee(route('logbook.pdf-viewer', $this->entryRevisi))
            ->assertSee(route('logbook.approve', $this->entryRevisi))
            ->assertSee(route('logbook.request-revisi', $this->entryRevisi));
        $this->assertSame(1, substr_count($response->getContent(), 'Buka PDF &amp; Anotasi'));
    }

    public function test_seminar_detail_separates_location_and_meeting_link_without_changing_document_routes(): void
    {
        $submission = SeminarSubmission::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'jenis' => SeminarSubmission::JENIS_PROPOSAL,
            'tanggal' => now()->addDays(7)->toDateString(),
            'waktu' => '13:00',
            'lokasi' => 'Ruang Rapat https://zoom.us/j/123456',
            'undangan_path' => 'seminar/undangan.pdf',
            'undangan_original_name' => 'surat-undangan-panjang.pdf',
            'materi_path' => 'seminar/proposal.pdf',
            'materi_original_name' => 'proposal.pdf',
            'status' => SeminarSubmission::STATUS_SUBMITTED,
        ]);

        $response = $this->actingAs($this->dosen)->get(route('seminar-submission.show', $submission));

        $response->assertOk()
            ->assertSee('Jadwal Seminar Proposal')
            ->assertSee('Ruang Rapat')
            ->assertSee('Buka Zoom')
            ->assertSee('Zoom Meeting')
            ->assertSee('surat-undangan-panjang.pdf')
            ->assertSee(route('seminar-submission.undangan-download', $submission))
            ->assertSee(route('seminar-submission.materi-preview', $submission))
            ->assertSee(route('seminar-submission.hardcopy-note', $submission))
            ->assertSee(route('dosen-sidang.index', ['submission' => $submission->id]));
        $this->assertStringNotContainsString('Ruang Rapat https://zoom.us', $response->getContent());
    }
}
