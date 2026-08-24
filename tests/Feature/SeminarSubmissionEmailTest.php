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

    public function test_email_menyertakan_lampiran_ics_dengan_dtend_satu_jam(): void
    {
        $mail = (new SeminarSubmissionNotification($this->submission))->toMail($this->dosen);

        $this->assertCount(1, $mail->rawAttachments);
        $attachment = $mail->rawAttachments[0];

        $this->assertSame('text/calendar; charset=utf-8', $attachment['options']['mime'] ?? null);
        $this->assertStringStartsWith('undangan-', (string) $attachment['name']);
        $this->assertStringEndsWith('.ics', (string) $attachment['name']);

        $ics = $attachment['data'];
        $timezone = config('app.timezone', 'UTC');
        $start = $this->submission->start();
        $end = $this->submission->end();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
        $this->assertSame(60, (int) $start->diffInMinutes($end));
        $this->assertStringContainsString('DTSTART;TZID='.$timezone.':'.$start->format('Ymd\THis'), $ics);
        $this->assertStringContainsString('DTEND;TZID='.$timezone.':'.$end->format('Ymd\THis'), $ics);
        $this->assertStringContainsString('SUMMARY:Seminar Proposal — Mhs Ics', $ics);
        $this->assertStringContainsString('LOCATION:Ruang Sidang A', $ics);

        // Semua baris ter-fold maksimal 75 karakter (RFC 5545).
        foreach (explode("\r\n", $ics) as $line) {
            $this->assertTrue(mb_strlen($line) <= 75, 'Baris ICS melebihi 75 karakter: '.$line);
        }
    }

    public function test_tautan_berbagi_bisa_diakses_guest_dan_menolak_signature_rusak(): void
    {
        $url = URL::temporarySignedRoute(
            'seminar-submission.shared-undangan',
            now()->addDay(),
            ['submission' => $this->submission->id]
        );

        // Guest (tanpa login) bisa unduh (BinaryFileResponse: cek status + header).
        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=undangan.pdf');

        // Signature dimanipulasi -> 403.
        $this->get($url.'&tampered=1')->assertForbidden();
    }

    public function test_end_default_satu_jam_ketika_tidak_ada_durasi(): void
    {
        $start = $this->submission->start();

        $this->assertSame('09:00', $start->format('H:i'));
        $this->assertSame('10:00', $this->submission->end()->format('H:i'));
    }
}
