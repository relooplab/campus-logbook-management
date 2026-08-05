<?php

namespace App\Console\Commands;

use App\Models\DirectorySubscription;
use App\Models\User;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Console\Command;

class NotifyExpiringDirectorySubscriptions extends Command
{
    protected $signature = 'directory:notify-expiring-subscriptions';

    protected $description = 'Kirim notifikasi ke system_admin untuk langganan direktori yang akan berakhir (H-7 & H-1) atau baru saja berakhir';

    public function handle(): int
    {
        $now = now();
        $h7 = $now->copy()->addDays(7);
        $h1 = $now->copy()->addDays(1);

        // Langganan aktif dengan ends_at dalam 7 hari ke depan (H-7 & H-1).
        $expiring = DirectorySubscription::where('status', DirectorySubscription::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '>=', $now)
            ->where('ends_at', '<=', $h7)
            ->get();

        // Langganan yang baru saja berakhir (ends_at kemarin atau hari ini, status masih active).
        $expired = DirectorySubscription::where('status', DirectorySubscription::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->where('ends_at', '>=', $now->copy()->subDay())
            ->get();

        // Penerima: semua system_admin.
        $systemAdmins = User::role('system_admin')->get();
        if ($systemAdmins->isEmpty()) {
            $this->warn('Tidak ada system_admin untuk menerima notifikasi.');

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($expiring as $sub) {
            $daysLeft = (int) $now->diffInDays($sub->ends_at);
            // Hanya kirim di H-7 dan H-1 (bukan setiap hari).
            if ($daysLeft !== 7 && $daysLeft !== 1) {
                continue;
            }

            foreach ($systemAdmins as $admin) {
                $admin->notify(new SubscriptionExpiringNotification($sub, 'expiring'));
                $sent++;
            }

            $this->line("Notifikasi H-{$daysLeft}: {$sub->scopeLabel()} \"{$sub->scopeName()}\" berakhir {$sub->ends_at->format('d M Y')}");
        }

        foreach ($expired as $sub) {
            foreach ($systemAdmins as $admin) {
                $admin->notify(new SubscriptionExpiringNotification($sub, 'expired'));
                $sent++;
            }

            $this->line("Notifikasi expired: {$sub->scopeLabel()} \"{$sub->scopeName()}\" berakhir {$sub->ends_at->format('d M Y')}");
        }

        $this->info("Selesai. {$sent} notifikasi terkirim.");

        return self::SUCCESS;
    }
}