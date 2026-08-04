<?php

namespace App\Notifications;

use App\Models\SeminarSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi ke dosen ketika mahasiswa mengirim bahan seminar/sidang.
 * Berisi detail jadwal lengkap (tanpa tombol/link di email).
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
            ->line('• Waktu: '.($submission->waktu?->format('H:i') ?? '—'))
            ->line('• Lokasi: '.($submission->lokasi ?: '—'))
            ->line('• Undangan sebagai: '.$submission->undanganSebagaiLabel());

        if ($submission->catatan_keterangan) {
            $mail->line('• Catatan: '.$submission->catatan_keterangan);
        }

        return $mail;
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
}