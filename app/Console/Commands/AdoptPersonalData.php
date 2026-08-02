<?php

namespace App\Console\Commands;

use App\Models\Institution;
use App\Models\MahasiswaTa;
use App\Models\Sidang;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Fase E: Bawa data pribadi dosen ke institusi.
 * Data mahasiswa pribadi (institution_id = NULL, pembimbing/penguji = dosen)
 * diadopsi ke institusi tujuan: institution_id diisi + opsi update users.
 */
class AdoptPersonalData extends Command
{
    protected $signature = 'ta:adopt-personal-data
                            {--dosen= : User ID dosen pemilik data}
                            {--institution= : Institution ID tujuan}
                            {--only=* : Batasi hanya TA tertentu (id)}
                            {--dry-run : Tampilkan tanpa mengubah}
                            {--include-users : Ikut update users.institution_id}
                            {--force : Adopsi meski institution_id sudah terisi}';

    protected $description = 'Adopsi data mahasiswa pribadi dosen ke institusi';

    public function handle(): int
    {
        $dosenId = (int) $this->option('dosen');
        $institutionId = (int) $this->option('institution');
        $dryRun = (bool) $this->option('dry-run');
        $includeUsers = (bool) $this->option('include-users');
        $force = (bool) $this->option('force');

        if (!$dosenId) {
            $this->error('Argumen --dosen wajib diisi.');
            return self::FAILURE;
        }
        if (!$institutionId || !Institution::find($institutionId)) {
            $this->error('Argumen --institution wajib & harus institusi valid.');
            return self::FAILURE;
        }

        $dosen = User::find($dosenId);
        if (!$dosen) {
            $this->error("Dosen (ID {$dosenId}) tidak ditemukan.");
            return self::FAILURE;
        }

        // Query TA milik dosen tsb (pembimbing 1/2 atau penguji 1/2) yg belum punya institusi.
        $query = MahasiswaTa::withoutGlobalScopes()
            ->where(function ($q) use ($dosenId) {
                $q->where('pembimbing_1_id', $dosenId)
                    ->orWhere('pembimbing_2_id', $dosenId)
                    ->orWhere('penguji_1_id', $dosenId)
                    ->orWhere('penguji_2_id', $dosenId);
            });

        if (!$force) {
            $query->whereNull('institution_id');
        }

        $only = array_filter($this->option('only') ?: []);
        if ($only) {
            $query->whereIn('id', $only);
        }

        $tas = $query->get();

        if ($tas->isEmpty()) {
            $this->info('Tidak ada data TA pribadi untuk diadopsi.');
            return self::SUCCESS;
        }

        $this->table(
            ['TA ID', 'Mahasiswa', 'Pembimbing 1', 'Pembimbing 2', 'Penguji 1', 'Penguji 2'],
            $tas->map(fn ($ta) => [
                $ta->id,
                $ta->mahasiswa?->name ?? $ta->user_id,
                $ta->pembimbing_1_id,
                $ta->pembimbing_2_id,
                $ta->penguji_1_id,
                $ta->penguji_2_id,
            ])
        );

        if ($dryRun) {
            $this->line("DRY-RUN: {$tas->count()} TA siap diadopsi ke institusi #{$institutionId}.");
            return self::SUCCESS;
        }

        $institution = Institution::find($institutionId);
        $adopted = 0;

        foreach ($tas as $ta) {
            $old = $ta->institution_id;
            $ta->institution_id = $institutionId;
            $ta->save();

            // Adopsi sidang terkait (penguji_id = dosen) yg belum institusi.
            $sidangs = Sidang::withoutGlobalScopes()
                ->where('penguji_id', $dosenId)
                ->where('mahasiswa_ta_id', $ta->id)
                ->whereNull('institution_id')
                ->get();
            foreach ($sidangs as $s) {
                $s->institution_id = $institutionId;
                $s->save();
            }

            // Adopsi akun mahasiswa pemilik TA (opsional).
            if ($includeUsers && $ta->user_id) {
                $mhs = User::find($ta->user_id);
                if ($mhs && !$mhs->institution_id) {
                    $mhs->institution_id = $institutionId;
                    $mhs->save();
                }
            }

            $adopted++;
            Log::channel('audit')->info('Adopt personal data', [
                'ta_id' => $ta->id,
                'dosen' => $dosenId,
                'institution' => $institutionId,
                'old' => $old,
                'new' => $institutionId,
                'waktu' => now()->toDateTimeString(),
            ]);
        }

        // Update dosen bergabung ke institusi (opsional).
        if ($includeUsers && !$dosen->institution_id) {
            $dosen->institution_id = $institutionId;
            $dosen->save();
        }

        $this->info("{$adopted} data TA diadopsi ke institusi '{$institution->institution_name}' (#{$institutionId}).");

        return self::SUCCESS;
    }
}
