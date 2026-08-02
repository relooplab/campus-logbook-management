<?php

namespace App\Console\Commands;

use App\Models\LogbookEntry;
use App\Models\MahasiswaTa;
use App\Models\User;
use App\Notifications\ReminderNotification;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'logbook:send-reminders
                            {--inactive-days=7 : Hari tanpa aktivitas mahasiswa}
                            {--queue-days=3 : Umur antrean review dosen (hari)}';

    protected $description = 'Kirim reminder harian untuk mahasiswa tidak aktif dan dosen dengan antrean review lama';

    public function handle(): int
    {
        $inactiveDays = (int) $this->option('inactive-days');
        $queueDays = (int) $this->option('queue-days');

        // 1) Mahasiswa yang belum membuat/mengirim entri dalam X hari.
        $cutoff = now()->subDays($inactiveDays);
        $tas = MahasiswaTa::whereDoesntHave('entries', function ($q) use ($cutoff) {
            $q->where(function ($qq) use ($cutoff) {
                $qq->where('status', '!=', LogbookEntry::STATUS_DRAFT)
                    ->where('created_at', '>=', $cutoff);
            });
        })->with('mahasiswa')->get();

        foreach ($tas as $ta) {
            if ($ta->mahasiswa) {
                $ta->mahasiswa->notify(new ReminderNotification(
                    "Anda belum mencatat bimbingan selama {$inactiveDays}+ hari. Silakan perbarui logbook Anda.",
                    url('/logbook/create'),
                ));
                $this->line("Reminder mahasiswa: {$ta->mahasiswa->email}");
            }
        }

        // 2) Dosen dengan antrean review (submitted) berumur > Y hari.
        $dosenIds = User::role('dosen')->pluck('id');
        foreach ($dosenIds as $id) {
            $oldCount = LogbookEntry::where('status', LogbookEntry::STATUS_SUBMITTED)
                ->where('submitted_at', '<', now()->subDays($queueDays))
                ->whereHas('mahasiswaTa', function ($q) use ($id) {
                    $q->where('pembimbing_1_id', $id)->orWhere('pembimbing_2_id', $id);
                })
                ->count();

            if ($oldCount > 0 && ($dosen = User::find($id))) {
                $dosen->notify(new ReminderNotification(
                    "Anda memiliki {$oldCount} entri menunggu review lebih dari {$queueDays} hari.",
                    url('/logbook'),
                ));
                $this->line("Reminder dosen: {$dosen->email} ({$oldCount} antrean)");
            }
        }

        $this->info('Reminder selesai.');

        return self::SUCCESS;
    }
}
