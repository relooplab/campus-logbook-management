<?php

namespace Tests\Feature;

use App\Models\MahasiswaTa;
use App\Models\SeminarSubmission;
use App\Models\User;
use App\Notifications\SeminarSubmissionNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Email notifikasi bahan seminar/sidang:
 *  - Berisi tautan detail + tautan langsung (signed URL) undangan & materi.
 *  - Menyertakan lampiran kalender .ics dengan DTEND = mulai + 60 menit
 *    (form belum punya isian durasi, default 1 jam).
 *  - Tautan berbagi bisa diakses tanpa login; signature rusak ditolak 403.
 */
class SeminarSubmissionEmailTest extends TestCase
{
    use DatabaseTransactions;

    private User $mhs;
    private User $dosen;
    private MahasiswaTa $ta;
    private SeminarSubmission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['mahasiswa', 'dosen'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->mhs = User::create([
            'name' => 'Mhs Ics',
            'email' => 'mhs-ics-'.uniqid().'@t.test',
            'password' => bcrypt('password'),
            'nim' => 'NIM'.substr(md5(uniqid()), 0, 8),
            'whatsapp' => '6281234567890',
            'registration_status' => 'active',
        ]);
        $this->mhs->assignRole('mahasiswa');

        $this->dosen = User::create([
            'name' => 'Dosen Ics',
            'email' => 'dosen-ics-'.uniqid().'@t.test',
            'password' => bcrypt('password'),
            'nidn' => 'NIDN'.substr(md5(uniqid()), 0, 10),
            'registration_status' => 'active',
        ]);
        $this->dosen->assignRole('dosen');


        $this->ta = MahasiswaTa::create([
            'user_id' => $this->mhs->id,
            'jenis' => MahasiswaTa::JENIS_TA,
            'pembimbing_1_id' => $this->dosen->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
            'fase' => 'proposal',
        ]);

        // File riil di disk local agar route download berhasil.
        Storage::disk('local')->put('seminar-materials/'.$this->ta->id.'/undangan.pdf', 'UNDANGAN-PDF');
        Storage::disk('local')->put('seminar-materials/'.$this->ta->id.'/materi.pdf', 'MATERI-PDF');

        $this->submission = SeminarSubmission::create([
            'mahasiswa_ta_id' => $this->ta->id,
            'jenis' => SeminarSubmission::JENIS_PROPOSAL,
            'tanggal' => now()->addDays(3)->toDateString(),
            'waktu' => '09:00',
            'lokasi' => 'Ruang Sidang A',
            'undangan_path' => 'seminar-materials/'.$this->ta->id.'/undangan.pdf',
            'undangan_original_name' => 'undangan.pdf',
            'undangan_kepada' => ['pembimbing_1'],
            'materi_path' => 'seminar-materials/'.$this->ta->id.'/materi.pdf',
            'materi_original_name' => 'materi.pdf',
            'catatan_hardcopy' => '',
            'catatan_keterangan' => 'Hadir tepat waktu.',
            'status' => SeminarSubmission::STATUS_SUBMITTED,
        ]);
    }

    protected function tearDown(): void
    {
        // DatabaseTransactions hanya mem-roll-back DB; bersihkan file fisik.
        Storage::disk('local')->deleteDirectory('seminar-materials/'.$this->ta?->id);

        parent::tearDown();
    }

    public function test_email_berisi_link_detail_dan_link_langsung_dokumen(): void
    {
        $mail = (new SeminarSubmissionNotification($this->submission))->toMail($this->dosen);
        $html = $mail->render();

        $this->assertStringContainsString(route('seminar-submission.show', $this->submission), $html);
        $this->assertStringContainsString('/shared/seminar-submission/'.$this->submission->id.'/undangan', $html);
        $this->assertStringContainsString('/shared/seminar-submission/'.$this->submission->id.'/materi', $html);
    }

    public function test_email_menggunakan_template_markdown_responsif(): void
    {
        $mail = (new SeminarSubmissionNotification($this->submission))->toMail($this->dosen);
        $html = $mail->render();

        // Panel detail jadwal.
        $this->assertStringContainsString('panel-content', $html);
        $this->assertStringContainsString('Detail Jadwal', $html);
        $this->assertStringContainsString('Seminar Proposal', $html);

        // Tombol aksi (mobile: full-width lewat media query framework).
        $this->assertStringContainsString('class="button', $html);
        // Label tombol mencerminkan aksi buka-di-browser (bukan unduh).
        $this->assertStringContainsString('Buka Surat Undangan di Browser', $html);
        $this->assertStringContainsString('Buka Materi di Browser', $html);
        $this->assertStringContainsString('Lihat Detail Bahan', $html);

        // Subcopy berisi fallback tautan dengan word-break.
        $this->assertStringContainsString('subcopy', $html);
        $this->assertStringContainsString('break-all', $html);
    }

    public function test_email_menyertakan_lampiran_ics_dengan_dtend_satu_jam(): void
    {
        $mail = (new SeminarSubmissionNotification($this->submission))->toMail($this->dosen);

        $this->assertCount(1, $mail->rawAttachments);
        $attachment = $mail->rawAttachments[0];

        $this->assertSame('text/calendar; charset=utf-8', $attachment['options']['mime'] ?? null);
        $this->assertStringStartsWith('undangan-', (string) $attachment['name']);
        $this->assertStringEndsWith('.ics', (string) $attachment['name']);

        $ics = $attachment['data'];
        // Unfold baris terlipat (RFC 5545) agar substring mudah diperiksa.
        $unfolded = str_replace("\r\n ", '', $ics);
        $start = $this->submission->start();
        $end = $this->submission->end();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
        $this->assertSame(60, (int) $start->diffInMinutes($end));
        // 09:00 WIB = 02:00 UTC; durasi default 1 jam -> selesai 03:00 UTC.
        // Tanggal pasti ikut serta (DTSTART memuat Ymd).
        $tanggal = $this->submission->tanggal->format('Ymd');
        $this->assertStringContainsString('DTSTART:'.$tanggal.'T020000Z', $ics);
        $this->assertStringContainsString('DTEND:'.$tanggal.'T030000Z', $ics);
        $this->assertStringContainsString('SUMMARY:Seminar Proposal — Mhs Ics', $ics);
        $this->assertStringContainsString('LOCATION:Ruang Sidang A', $ics);

        // Narasi DESCRIPTION selaras dengan detail badan email.
        $this->assertStringContainsString('Undangan Seminar Proposal atas nama mahasiswa Mhs Ics.', $unfolded);
        $this->assertStringContainsString('Jenis: Seminar Proposal', $unfolded);
        $this->assertStringContainsString('Mahasiswa: Mhs Ics', $unfolded);
        // Koma di-escape menjadi \, (RFC 5545) — klien kalender menampilkan normal.
        $this->assertStringContainsString('Tanggal: '.str_replace(',', '\\,', $start->format('l, d F Y')), $unfolded);
        $this->assertStringContainsString('Waktu: 09:00 – 10:00 (60 menit)', $unfolded);
        $this->assertStringContainsString('Lokasi: Ruang Sidang A', $unfolded);
        $this->assertStringContainsString('Diundang: Pembimbing 1 — Dosen Ics', $unfolded);
        $this->assertStringContainsString('Catatan: Hadir tepat waktu.', $unfolded);

        // Tautan dokumen ikut disertakan di deskripsi kalender.
        $this->assertStringContainsString('Link Surat Undangan:', $unfolded);
        $this->assertStringContainsString('/shared/seminar-submission/'.$this->submission->id.'/undangan', $unfolded);
        $this->assertStringContainsString('Link Materi:', $unfolded);
        $this->assertStringContainsString('/shared/seminar-submission/'.$this->submission->id.'/materi', $unfolded);

        // Semua baris ter-fold maksimal 75 oktet (RFC 5545).
        foreach (explode("\r\n", $ics) as $line) {
            $this->assertTrue(strlen($line) <= 75, 'Baris ICS melebihi 75 oktet: '.$line);
        }
    }

    public function test_dosen_bisa_buka_preview_pdf_tanpa_download_dari_aplikasi(): void
    {
        // Route preview dalam aplikasi (auth): PDF dirender inline, bukan attachment.
        $this->actingAs($this->dosen)
            ->get(route('seminar-submission.undangan-preview', $this->submission))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename=undangan.pdf');

        $this->actingAs($this->dosen)
            ->get(route('seminar-submission.materi-preview', $this->submission))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename=materi.pdf');
    }

    public function test_tautan_berbagi_bisa_diakses_guest_dan_menolak_signature_rusak(): void
    {
        $url = URL::temporarySignedRoute(
            'seminar-submission.shared-undangan',
            now()->addDay(),
            ['submission' => $this->submission->id]
        );

        // Guest (tanpa login) bisa akses. PDF ditampilkan inline di browser
        // (preview tanpa unduhan) — Content-Type PDF + Content-Disposition inline.
        $this->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename=undangan.pdf');

        // Signature dimanipulasi -> 403.
        $this->get($url.'&tampered=1')->assertForbidden();
    }

    public function test_end_default_satu_jam_ketika_tidak_ada_durasi(): void
    {
        $start = $this->submission->start();

        $this->assertSame('09:00', $start->format('H:i'));
        $this->assertSame('10:00', $this->submission->end()->format('H:i'));
    }

    public function test_mahasiswa_pengirim_menerima_email_sama_dengan_narasi_khusus(): void
    {
        $mail = (new SeminarSubmissionNotification($this->submission, 'mahasiswa'))->toMail($this->mhs);
        $html = $mail->render();

        // Sapaan & narasi khusus penerima mahasiswa (bukti kirim).
        $this->assertStringContainsString('Halo Mhs Ics', $html);
        $this->assertStringContainsString('Anda telah mengirim bahan', $html);

        // Struktur email tetap lengkap: tombol dokumen & detail.
        $this->assertStringContainsString('Buka Surat Undangan di Browser', $html);
        $this->assertStringContainsString('Buka Materi di Browser', $html);
        $this->assertStringContainsString('Lihat Detail Bahan', $html);
        $this->assertStringContainsString('break-all', $html);

        // Tetap ada lampiran .ics.
        $this->assertCount(1, $mail->rawAttachments);
        $this->assertStringEndsWith('.ics', (string) $mail->rawAttachments[0]['name']);
    }
}
