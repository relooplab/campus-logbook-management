<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reminder harian 08:00 Asia/Jakarta.
Schedule::command('logbook:send-reminders')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Pengingat mahasiswa tidak aktif bimbingan > 3 minggu (email, CC pembimbing 1).
Schedule::command('ta:notify-inactive')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Weekly digest (setiap Senin 07:00).
Schedule::command('ta:weekly-digest')
    ->weeklyOn(1, '07:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Bersihkan file lampiran orphan (mingguan, buffer 30 hari).
Schedule::command('files:prune-orphans')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Bersihkan personal access token yang kedaluwarsa.
Schedule::command('sanctum:prune-expired')
    ->daily()
    ->timezone('Asia/Jakarta');
