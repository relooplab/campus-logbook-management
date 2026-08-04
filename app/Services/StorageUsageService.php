<?php

namespace App\Services;

use App\Models\LogbookEntry;
use App\Models\LogbookHarianKp;
use App\Models\MahasiswaTa;
use App\Models\SeminarSubmission;
use App\Models\ThesisFinalization;
use App\Models\User;
use App\Models\WorkspaceFile;
use App\Support\Feature;
use Illuminate\Support\Facades\Storage;

/**
 * Menghitung pemakaian penyimpanan (bytes) untuk seorang user.
 *
 * Untuk dosen: total dari workspace pribadi dosen + seluruh file & lampiran
 * dari mahasiswa yang dibimbingnya (logbook, revisi, workspace mahasiswa,
 * seminar-materials, finalization, logbook harian KP).
 * Untuk mahasiswa: total dari workspace & lampiran miliknya.
 *
 * Quota dibebankan ke dosen (pembimbing 1, fallback pembimbing 2).
 */
class StorageUsageService
{
    /**
     * Total pemakaian (bytes) untuk user.
     */
    public function totalBytes(User $user): int
    {
        if ($user->isDosen()) {
            return $this->dosenBytes($user);
        }

        return $this->mahasiswaBytes($user);
    }

    /**
     * Pemakaian dosen = workspace pribadi + data mahasiswa bimbingan.
     */
    private function dosenBytes(User $dosen): int
    {
        $total = 0;

        // 1. Workspace pribadi dosen.
        $total += WorkspaceFile::where('user_id', $dosen->id)->sum('size');

        // 1b. Foto profil dosen sendiri.
        $total += $this->diskSize('public', $dosen->profile_photo_path);

        // 2. Program (TA/KP) yang dibebani ke dosen ini.
        //    Pembimbing 1 (valid) -> fallback pembimbing 2. Penguji tidak dihitung.
        $programIds = $this->dosenProgramIds($dosen);

        // 3. Workspace mahasiswa dari program yang dibebani.
        $total += WorkspaceFile::whereIn('mahasiswa_ta_id', $programIds)->sum('size');

        // 3b. Foto profil mahasiswa dari program yang dibebani.
        $total += $this->mahasiswaProfilePhotoBytes($programIds);

        // 4. Lampiran logbook & revisi dari program yang dibebani.
        $total += LogbookEntry::whereIn('mahasiswa_ta_id', $programIds)
            ->whereNotNull('lampiran_path')
            ->sum('lampiran_size');

        // 4b. Catatan perbaikan (PDF auto-generate) dari program yang dibebani.
        $total += LogbookEntry::whereIn('mahasiswa_ta_id', $programIds)
            ->whereNotNull('catatan_perbaikan_path')
            ->sum('catatan_perbaikan_size');

        // 5. Foto logbook harian KP dari program yang dibebani.
        $total += $this->logbookHarianKpBytes($programIds);

        // 6. File seminar-materials dari program yang dibebani.
        $total += $this->seminarBytes($programIds);

        // 7. File finalization dari program yang dibebani.
        $total += $this->finalizationBytes($programIds);

        return (int) $total;
    }

    /**
     * Pemakaian mahasiswa = workspace + lampiran miliknya.
     */
    private function mahasiswaBytes(User $mahasiswa): int
    {
        $total = 0;

        $taIds = $mahasiswa->mahasiswaPrograms()->pluck('id');

        $total += WorkspaceFile::whereIn('mahasiswa_ta_id', $taIds)->sum('size');

        // Foto profil mahasiswa sendiri.
        $total += $this->diskSize('public', $mahasiswa->profile_photo_path);

        $total += LogbookEntry::whereIn('mahasiswa_ta_id', $taIds)
            ->whereNotNull('lampiran_path')
            ->sum('lampiran_size');

        // Catatan perbaikan (PDF auto-generate).
        $total += LogbookEntry::whereIn('mahasiswa_ta_id', $taIds)
            ->whereNotNull('catatan_perbaikan_path')
            ->sum('catatan_perbaikan_size');

        // Foto logbook harian KP.
        $total += $this->logbookHarianKpBytes($taIds);

        // File seminar-materials.
        $total += $this->seminarBytes($taIds);

        // File finalization.
        $total += $this->finalizationBytes($taIds);

        return (int) $total;
    }

    /**
     * ID program (TA/KP) yang dibebani ke dosen tertentu.
     * Pembimbing 1 (valid) -> fallback pembimbing 2. Penguji tidak dihitung.
     */
    public function dosenProgramIds(User $dosen): \Illuminate\Support\Collection
    {
        return MahasiswaTa::where(function ($q) use ($dosen) {
            $q->where('pembimbing_1_id', $dosen->id)
              ->whereHas('pembimbing1', fn ($u) => $u->where('registration_status', 'approved'))
              ->orWhere(function ($q2) use ($dosen) {
                  $q2->where('pembimbing_2_id', $dosen->id)
                     ->where(function ($q3) {
                         $q3->whereNull('pembimbing_1_id')
                            ->orWhereDoesntHave('pembimbing1', fn ($u) => $u->where('registration_status', 'approved'));
                     });
              });
        })->pluck('id');
    }

    /**
     * Total ukuran foto profil mahasiswa (pemilik utama) dari sekumpulan program.
     */
    private function mahasiswaProfilePhotoBytes($programIds): int
    {
        if ($programIds->isEmpty()) {
            return 0;
        }

        $total = 0;
        $paths = MahasiswaTa::whereIn('id', $programIds)
            ->with('mahasiswa:id,profile_photo_path')
            ->get()
            ->pluck('mahasiswa.profile_photo_path')
            ->filter();

        foreach ($paths as $path) {
            $total += $this->diskSize('public', $path);
        }

        return (int) $total;
    }

    private function logbookHarianKpBytes($programIds): int
    {
        if ($programIds->isEmpty()) {
            return 0;
        }

        $total = 0;
        $entries = LogbookHarianKp::whereIn('mahasiswa_ta_id', $programIds)
            ->where(fn ($q) => $q->whereNotNull('foto_1')->orWhereNotNull('foto_2'))
            ->get(['foto_1', 'foto_2']);

        foreach ($entries as $e) {
            foreach (['foto_1', 'foto_2'] as $col) {
                if ($e->{$col}) {
                    $total += $this->diskSize('local', $e->{$col});
                }
            }
        }

        return (int) $total;
    }

    private function seminarBytes($programIds): int
    {
        if ($programIds->isEmpty()) {
            return 0;
        }

        $total = 0;
        $submissions = SeminarSubmission::whereIn('mahasiswa_ta_id', $programIds)
            ->get(['undangan_path', 'materi_path']);

        foreach ($submissions as $s) {
            if ($s->undangan_path) {
                $total += $this->diskSize('local', $s->undangan_path);
            }
            if ($s->materi_path) {
                $total += $this->diskSize('local', $s->materi_path);
            }
        }

        return (int) $total;
    }

    private function finalizationBytes($programIds): int
    {
        if ($programIds->isEmpty()) {
            return 0;
        }

        $total = 0;
        $finalizations = ThesisFinalization::whereIn('mahasiswa_ta_id', $programIds)
            ->get(['cover_path', 'pengesahan_path', 'full_file_path']);

        foreach ($finalizations as $f) {
            foreach (['cover_path', 'pengesahan_path', 'full_file_path'] as $col) {
                if ($f->{$col}) {
                    $total += $this->diskSize('local', $f->{$col});
                }
            }
        }

        return (int) $total;
    }

    /**
     * Ukuran file di disk tertentu (bytes), aman jika file tidak ada.
     */
    private function diskSize(string $disk, ?string $path): int
    {
        if (!$path) {
            return 0;
        }

        try {
            return (int) Storage::disk($disk)->size($path);
        } catch (\Throwable $ex) {
            return 0;
        }
    }

    /**
     * Cek apakah user (dosen) masih punya sisa kuota untuk upload sebesar $incomingBytes.
     * limit = 0 artinya unlimited (tidak dibatasi).
     */
    public function assertCanUpload(User $dosen, int $incomingBytes): void
    {
        $limitMb = Feature::storageLimitMb($dosen);
        if ($limitMb <= 0) {
            return; // unlimited
        }

        $limitBytes = $limitMb * 1048576;
        $used = $this->totalBytes($dosen);

        if ($used + $incomingBytes > $limitBytes) {
            $remaining = max(0, $limitBytes - $used);
            abort(422, 'Kuota penyimpanan tidak mencukupi. Terpakai '.$this->formatBytes($used)
                .' dari '.$this->formatBytes($limitBytes)
                .' (sisa '.$this->formatBytes($remaining).').');
        }
    }

    /**
     * Format bytes menjadi string ramah-baca (mis. "3.2 GB").
     */
    public function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 1).' '.$units[$i];
    }
}