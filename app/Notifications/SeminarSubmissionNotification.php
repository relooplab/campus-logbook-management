<?php

namespace App\Notifications;

use App\Models\Institution;
use App\Models\SeminarSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Notifikasi ke dosen ketika mahasiswa mengirim bahan seminar/sidang.
 * Berisi detail jadwal lengkap, tautan langsung dokumen (signed URL dengan
 * masa berlaku terbatas), dan lampiran kalender .ics.
 */
class SeminarSubmissionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SeminarSubmission $submission)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Resolve config mail/branding sesuai institusi penerima (queue worker).
        Institution::forUser($notifiable)->applyToConfig();

        $submission = $this->submission;
        $ta = $submission->mahasiswaTa;
        $mahasiswa = $ta?->mahasiswa;

        $mail = (new MailMessage)
            ->subject('Bahan '.$submission->jenisLabel().' Dikirim')
            ->greeting('Halo '.$notifiable->name)
            ->line('Mahasiswa '.($mahasiswa?->name ?? '—').' telah mengirim bahan '.$submission->jenisLabel().'.')
            ->line('')
            ->line('Detail Jadwal:')
            ->line('• Jenis: '.$submission->jenisLabel())
            ->line('• Mahasiswa: '.($mahasiswa?->name ?? '—'))
            ->line('• Tanggal: '.($submission->tanggal?->format('l, d F Y') ?? '—'))
            ->line('• Waktu: '.($submission->waktu?->format('H:i') ?? '—').' – '.$submission->end()->format('H:i').' ('.SeminarSubmission::DEFAULT_DURASI_MENIT.' menit)')
            ->line('• Lokasi: '.($submission->lokasi ?: '—'))
            ->line('• Diundang: '.$submission->undanganKepadaLabel());

        if ($submission->catatan_keterangan) {
            $mail->line('• Catatan: '.$submission->catatan_keterangan);
        }

        $mail->line('')
            ->line('Unduh dokumen (tautan sementara, berlaku hingga lewat jadwal):')
            ->line('[Surat Undangan — '.$submission->undangan_original_name.']('.$this->signedUrl('undangan').')');

        if ($submission->materi_path) {
            $mail->line('[Materi — '.($submission->materi_original_name ?: 'file materi').']('.$this->signedUrl('materi').')');
        }

        return $mail
            ->action('Lihat Detail Bahan', route('seminar-submission.show', $submission))
            ->line('')
            ->line('Lampiran .ics memuat jadwal di atas — klik untuk menambahkannya ke kalender Anda.')
            ->attachData(
                $this->buildIcs($notifiable),
                'undangan-'.$submission->id.'.ics',
                ['mime' => 'text/calendar; charset=utf-8']
            );
    }

    public function toArray(object $notifiable): array
    {
        $submission = $this->submission;
        $mahasiswa = $submission->mahasiswaTa?->mahasiswa;

        return [
            'message' => 'Mahasiswa '.($mahasiswa?->name ?? '—').' mengirim bahan '.$submission->jenisLabel().'.',
            'url' => route('seminar-submission.show', $submission),
        ];
    }

    /**
     * Tautan berbagi dokumen (signed URL) dengan masa berlaku terbatas.
     */
    private function signedUrl(string $jenis): string
    {
        $route = $jenis === 'materi'
            ? 'seminar-submission.shared-materi'
            : 'seminar-submission.shared-undangan';

        return URL::temporarySignedRoute(
            $route,
            $this->submission->sharedLinkExpiration(),
            ['submission' => $this->submission->id]
        );
    }

    /**
     * Bangun isi lampiran kalender (.ics / RFC 5545).
     * DTEND default = waktu mulai + DEFAULT_DURASI_MENIT karena form
     * pemberian bahan belum memiliki isian durasi.
     */
    private function buildIcs(object $notifiable): string
    {
        $submission = $this->submission;
        $mahasiswa = $submission->mahasiswaTa?->mahasiswa;
        $timezone = config('app.timezone', 'UTC');
        $start = $submission->start();
        $end = $submission->end();

        $description = 'Undangan '.$submission->jenisLabel()
            .' atas nama mahasiswa '.($mahasiswa?->name ?? '—').'.'
            ."\nDiundang: ".$submission->undanganKepadaLabel()
            .($submission->catatan_keterangan ? "\nCatatan: ".$submission->catatan_keterangan : '');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Campus Logbook Management//Bahan Seminar Sidang//ID',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:bahan-seminar-'.$submission->id.'@'.$this->icsDomain(),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART;TZID='.$timezone.':'.$start->format('Ymd\THis'),
            'DTEND;TZID='.$timezone.':'.$end->format('Ymd\THis'),
            'SUMMARY:'.$this->escapeIcs($submission->jenisLabel().' — '.($mahasiswa?->name ?? 'Mahasiswa')),
            'LOCATION:'.$this->escapeIcs((string) ($submission->lokasi ?? '')),
            'DESCRIPTION:'.$this->escapeIcs($description),
            'ORGANIZER:MAILTO:'.($mahasiswa?->email ?: 'no-reply@example.com'),
            'ATTENDEE:MAILTO:'.$notifiable->email,
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return $this->foldLines(implode("\r\n", $lines)."\r\n");
    }

    /**
     * Escape nilai teks ICS sesuai RFC 5545 (backslash, newline, koma, titik koma).
     */
    private function escapeIcs(string $value): string
    {
        return str_replace(
            ["\\", "\r\n", "\r", "\n", ";", ","],
            ["\\\\", "\\n", "\\n", "\\n", "\\;", "\\,"],
            $value
        );
    }

    /**
     * Line-folding ICS: maksimal 75 karakter per baris, kelanjutan baris
     * didahului CRLF + satu spasi.
     */
    private function foldLines(string $content): string
    {
        $folded = [];

        foreach (explode("\r\n", $content) as $line) {
            if (mb_strlen($line) <= 75) {
                $folded[] = $line;
                continue;
            }

            $folded[] = implode("\r\n ", mb_str_split($line, 75));
        }

        return implode("\r\n", $folded);
    }

    /**
     * Host aplikasi untuk komponen UID ICS.
     */
    private function icsDomain(): string
    {
        return (string) (parse_url(config('app.url', ''), PHP_URL_HOST) ?: 'localhost');
    }
}