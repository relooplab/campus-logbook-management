<?php

namespace App\Console\Commands;

use App\Models\Achievement;
use App\Models\MahasiswaTa;
use App\Services\AchievementService;
use Illuminate\Console\Command;

class SyncAchievements extends Command
{
    protected $signature = 'achievements:sync {--evaluate : Evaluasi ulang semua program TA setelah katalog disinkronkan}';

    protected $description = 'Sinkronkan katalog achievement dan opsional backfill unlock historis';

    public function handle(AchievementService $service): int
    {
        $now = now();
        $rows = collect(Achievement::definitions())
            ->map(fn (array $definition, string $code) => [
                'code' => $code,
                'icon' => $definition[0],
                'name' => $definition[1],
                'description' => $definition[2],
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        Achievement::upsert($rows, ['code'], ['icon', 'name', 'description', 'updated_at']);
        $this->info('Katalog '.count($rows).' achievement tersinkronkan.');

        if (! $this->option('evaluate')) {
            return self::SUCCESS;
        }

        $evaluated = 0;
        MahasiswaTa::withoutGlobalScopes()
            ->where('jenis', MahasiswaTa::JENIS_TA)
            ->with('mahasiswa')
            ->orderBy('id')
            ->eachById(function (MahasiswaTa $ta) use ($service, &$evaluated) {
                $service->evaluateForProgram($ta);
                $evaluated++;
            });

        $this->info("{$evaluated} program TA dievaluasi ulang.");

        return self::SUCCESS;
    }
}
